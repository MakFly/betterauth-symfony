<?php

declare(strict_types=1);

namespace Demo\Controller;

use BetterAuth\Symfony\Feature\DeviceService;
use BetterAuth\Symfony\Feature\OneTimeTokenService;
use BetterAuth\Symfony\Feature\SecurityMonitoringService;
use BetterAuth\Symfony\Feature\TenantMembershipService;
use BetterAuth\Symfony\Feature\TotpService;
use BetterAuth\Symfony\Token\RefreshRotationStatus;
use BetterAuth\Symfony\Token\RefreshTokenManager;
use Demo\Entity\DemoAccount;
use Demo\Security\ApiRateLimiter;
use Demo\Security\DemoTotpEnrollmentService;
use Demo\Storage\DoctrineTenantMembershipStore;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

final class DemoController extends AbstractController
{
    #[Route('/', name: 'demo_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('home.html.twig');
    }

    #[Route('/api/register', methods: ['POST'])]
    public function register(Request $request, EntityManagerInterface $entities, DoctrineTenantMembershipStore $tenants, ApiRateLimiter $limiter): JsonResponse
    {
        $payload = $request->getPayload()->all();
        $email = is_string($payload['email'] ?? null) ? $payload['email'] : '';
        $password = is_string($payload['password'] ?? null) ? $payload['password'] : '';
        if (!$limiter->accepts($request, $email)) {
            return $this->tooMany();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
            return $this->problem('invalid_registration', 'Use a valid email and a password of at least 12 characters.', 422);
        }
        if (!$entities->getRepository(DemoAccount::class)->findOneBy(['email' => strtolower($email)]) instanceof DemoAccount) {
            $account = new DemoAccount($email, $password);
            $entities->persist($account);
            $entities->flush();
            $tenants->grant($account->getUserIdentifier(), 'demo');
        }

        return new JsonResponse(['status' => 'registration_received'], 202);
    }

    #[Route('/api/login', methods: ['POST'])]
    public function login(Request $request, EntityManagerInterface $entities, RefreshTokenManager $refresh, ApiRateLimiter $limiter): JsonResponse
    {
        $payload = $request->getPayload()->all();
        $email = is_string($payload['email'] ?? null) ? strtolower($payload['email']) : '';
        $password = is_string($payload['password'] ?? null) ? $payload['password'] : '';
        if (!$limiter->accepts($request, $email)) {
            return $this->tooMany();
        }
        $account = $entities->getRepository(DemoAccount::class)->findOneBy(['email' => $email]);
        if (!$account instanceof DemoAccount || !password_verify($password, $account->passwordHash())) {
            return $this->problem('invalid_credentials', 'Invalid credentials.', 401);
        }

        return $this->tokenPair($refresh->issue($account->getUserIdentifier()));
    }

    #[Route('/api/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $account = $this->account();
        if ($account === null) {
            return $this->problem('unauthenticated', 'An access token is required.', 401);
        }

        return new JsonResponse(['user_id' => $account->getUserIdentifier(), 'email' => $account->email()]);
    }

    #[Route('/api/refresh', methods: ['POST'])]
    public function refresh(Request $request, RefreshTokenManager $refresh): JsonResponse
    {
        $rawToken = $request->getPayload()->get('refresh_token');
        if (!is_string($rawToken) || $rawToken === '') {
            return $this->problem('invalid_refresh', 'A refresh token is required.', 400);
        }
        $outcome = $refresh->rotate($rawToken);
        if ($outcome->status === RefreshRotationStatus::Rotated && $outcome->tokens !== null) {
            return $this->tokenPair($outcome->tokens);
        }

        return $this->problem($outcome->status === RefreshRotationStatus::Replayed ? 'refresh_replayed' : 'invalid_refresh', 'The refresh token cannot be used.', 401);
    }

    #[Route('/api/logout', methods: ['POST'])]
    public function logout(Request $request, RefreshTokenManager $refresh): Response
    {
        $rawToken = $request->getPayload()->get('refresh_token');
        if (!is_string($rawToken) || $rawToken === '' || !$refresh->revoke($rawToken)) {
            return $this->problem('invalid_refresh', 'The refresh token cannot be revoked.', 400);
        }

        return new Response('', 204);
    }

    #[Route('/api/logout/all', methods: ['POST'])]
    public function logoutAll(RefreshTokenManager $refresh): Response|JsonResponse
    {
        $account = $this->account();
        if ($account === null) {
            return $this->problem('unauthenticated', 'An access token is required.', 401);
        }
        $refresh->revokeAll($account->getUserIdentifier());

        return new Response('', 204);
    }

    #[Route('/api/totp/enroll', methods: ['POST'])]
    public function enrollTotp(Request $request, DemoTotpEnrollmentService $enrollment, ApiRateLimiter $limiter): JsonResponse
    {
        $account = $this->account();
        if ($account === null) {
            return $this->problem('unauthenticated', 'An access token is required.', 401);
        }
        if (!$limiter->accepts($request, $account->getUserIdentifier())) {
            return $this->tooMany();
        }
        $password = $request->getPayload()->get('password');
        if (!is_string($password) || !password_verify($password, $account->passwordHash())) {
            return $this->problem('reauthentication_required', 'Current password verification is required.', 401);
        }

        return new JsonResponse($enrollment->begin($account->getUserIdentifier(), $account->email()), 201);
    }

    #[Route('/api/totp/confirm', methods: ['POST'])]
    public function confirmTotp(Request $request, DemoTotpEnrollmentService $enrollment, ApiRateLimiter $limiter): JsonResponse
    {
        $account = $this->account();
        $code = $request->getPayload()->get('code');
        if ($account === null) {
            return $this->problem('unauthenticated', 'An access token is required.', 401);
        }
        if (!$limiter->accepts($request, $account->getUserIdentifier())) {
            return $this->tooMany();
        }
        if (!is_string($code) || !$enrollment->confirm($account->getUserIdentifier(), $code)) {
            return $this->problem('invalid_totp_confirmation', 'The pending TOTP code is invalid or expired.', 422);
        }

        return new JsonResponse(['confirmed' => true]);
    }

    #[Route('/api/totp/verify', methods: ['POST'])]
    public function verifyTotp(Request $request, ApiRateLimiter $limiter, #[Autowire(service: 'better_auth.feature.totp')] TotpService $totp): JsonResponse
    {
        $account = $this->account();
        $code = $request->getPayload()->get('code');
        if ($account === null) {
            return $this->problem('unauthenticated', 'An access token is required.', 401);
        }
        if (!$limiter->accepts($request, $account->getUserIdentifier())) {
            return $this->tooMany();
        }
        if (!is_string($code) || !$totp->verify($account->getUserIdentifier(), $code)) {
            return $this->problem('invalid_totp', 'The TOTP code is invalid.', 422);
        }

        return new JsonResponse(['verified' => true]);
    }

    #[Route('/api/magic-link', methods: ['POST'])]
    public function issueMagicLink(Request $request, EntityManagerInterface $entities, MailerInterface $mailer, ApiRateLimiter $limiter, #[Autowire(service: 'better_auth.feature.magic_link')] OneTimeTokenService $magic): JsonResponse
    {
        $email = $request->getPayload()->get('email');
        if (!$limiter->accepts($request, is_string($email) ? $email : null)) {
            return $this->tooMany();
        }
        if (is_string($email)) {
            $account = $entities->getRepository(DemoAccount::class)->findOneBy(['email' => strtolower($email)]);
            if ($account instanceof DemoAccount) {
                $this->sendOneTimeEmail($mailer, $account->email(), 'Your BetterAuth demo magic link', 'magic link', $magic->issue($account->getUserIdentifier()));
            }
        }

        return new JsonResponse(['status' => 'accepted', 'message' => 'If an account is eligible, instructions will be sent.']);
    }

    #[Route('/api/magic-link/consume', methods: ['POST'])]
    public function consumeMagicLink(Request $request, ApiRateLimiter $limiter, #[Autowire(service: 'better_auth.feature.magic_link')] OneTimeTokenService $magic, RefreshTokenManager $refresh): JsonResponse
    {
        $token = $request->getPayload()->get('token');
        if (!$limiter->accepts($request, is_string($token) ? $token : null)) {
            return $this->tooMany();
        }
        $userIdentifier = is_string($token) ? $magic->consume($token) : null;
        if ($userIdentifier === null) {
            return $this->problem('invalid_magic_link', 'The magic link cannot be used.', 400);
        }

        return $this->tokenPair($refresh->issue($userIdentifier));
    }

    #[Route('/api/password-reset', methods: ['POST'])]
    public function issuePasswordReset(Request $request, EntityManagerInterface $entities, MailerInterface $mailer, ApiRateLimiter $limiter, #[Autowire(service: 'better_auth.feature.email_reset')] OneTimeTokenService $reset): JsonResponse
    {
        $email = $request->getPayload()->get('email');
        if (!$limiter->accepts($request, is_string($email) ? $email : null)) {
            return $this->tooMany();
        }
        if (is_string($email)) {
            $account = $entities->getRepository(DemoAccount::class)->findOneBy(['email' => strtolower($email)]);
            if ($account instanceof DemoAccount) {
                $this->sendOneTimeEmail($mailer, $account->email(), 'Your BetterAuth demo password reset', 'password reset', $reset->issue($account->getUserIdentifier()));
            }
        }

        return new JsonResponse(['status' => 'accepted', 'message' => 'If an account is eligible, instructions will be sent.']);
    }

    #[Route('/api/password-reset/consume', methods: ['POST'])]
    public function consumePasswordReset(Request $request, EntityManagerInterface $entities, RefreshTokenManager $refresh, ApiRateLimiter $limiter, #[Autowire(service: 'better_auth.feature.email_reset')] OneTimeTokenService $reset): JsonResponse
    {
        $token = $request->getPayload()->get('token');
        $password = $request->getPayload()->get('password');
        if (!$limiter->accepts($request, is_string($token) ? $token : null)) {
            return $this->tooMany();
        }
        if (!is_string($password) || strlen($password) < 12) {
            return $this->problem('invalid_reset', 'The password reset cannot be used.', 400);
        }

        return $entities->wrapInTransaction(function () use ($token, $reset, $entities, $password, $refresh): JsonResponse {
            $userIdentifier = is_string($token) ? $reset->consume($token) : null;
            $account = is_string($userIdentifier) ? $entities->find(DemoAccount::class, $userIdentifier) : null;
            if (!$account instanceof DemoAccount) {
                return $this->problem('invalid_reset', 'The password reset cannot be used.', 400);
            }
            $account->changePassword($password);
            $refresh->revokeAll($account->getUserIdentifier());
            $entities->flush();

            return new JsonResponse(['status' => 'password_updated']);
        });
    }

    #[Route('/api/guest', methods: ['POST'])]
    public function issueGuest(Request $request, ApiRateLimiter $limiter, #[Autowire(service: 'better_auth.feature.guest')] OneTimeTokenService $guest): JsonResponse
    {
        if (!$limiter->accepts($request)) {
            return $this->tooMany();
        }
        $identifier = 'guest:'.bin2hex(random_bytes(8));

        return new JsonResponse(['guest_token' => $guest->issue($identifier)], 201);
    }

    #[Route('/api/guest/consume', methods: ['POST'])]
    public function consumeGuest(Request $request, ApiRateLimiter $limiter, #[Autowire(service: 'better_auth.feature.guest')] OneTimeTokenService $guest): JsonResponse
    {
        $token = $request->getPayload()->get('token');
        if (!$limiter->accepts($request, is_string($token) ? $token : null)) {
            return $this->tooMany();
        }
        $identifier = is_string($token) ? $guest->consume($token) : null;
        if ($identifier === null) {
            return $this->problem('invalid_guest_token', 'The guest token cannot be used.', 400);
        }

        return new JsonResponse(['guest_id' => $identifier]);
    }

    #[Route('/api/devices', methods: ['POST'])]
    public function recordDevice(Request $request, #[Autowire(service: 'better_auth.feature.device')] DeviceService $devices): JsonResponse
    {
        $account = $this->account();
        if ($account === null) {
            return $this->problem('unauthenticated', 'An access token is required.', 401);
        }
        $fingerprint = $devices->record($account->getUserIdentifier(), (string) $request->headers->get('User-Agent', ''), (string) $request->getClientIp(), ['label' => 'demo']);

        return new JsonResponse(['fingerprint' => $fingerprint], 201);
    }

    #[Route('/api/monitoring', methods: ['POST'])]
    public function recordMonitoring(Request $request, #[Autowire(service: 'better_auth.feature.monitoring')] SecurityMonitoringService $monitoring): JsonResponse
    {
        $account = $this->account();
        if ($account === null) {
            return $this->problem('unauthenticated', 'An access token is required.', 401);
        }
        $payload = $request->getPayload()->all();
        $type = is_string($payload['type'] ?? null) ? $payload['type'] : 'demo_event';
        $severity = is_string($payload['severity'] ?? null) ? $payload['severity'] : 'info';
        $monitoring->record($account->getUserIdentifier(), $type, $severity, ['source' => 'demo']);

        return new JsonResponse(['recorded' => true], 201);
    }

    #[Route('/api/tenant/{tenant}', methods: ['GET'])]
    public function tenant(string $tenant, #[Autowire(service: 'better_auth.feature.multi_tenant')] TenantMembershipService $memberships): JsonResponse
    {
        $account = $this->account();
        if ($account === null) {
            return $this->problem('unauthenticated', 'An access token is required.', 401);
        }
        if (!$memberships->allows($account->getUserIdentifier(), $tenant)) {
            return $this->problem('tenant_denied', 'The tenant is not available to this account.', 403);
        }

        return new JsonResponse(['tenant' => $tenant, 'allowed' => true]);
    }

    private function account(): ?DemoAccount
    {
        $user = $this->getUser();

        return $user instanceof DemoAccount ? $user : null;
    }

    private function tokenPair(\BetterAuth\Symfony\Token\TokenPair $tokens): JsonResponse
    {
        return new JsonResponse(['access_token' => $tokens->accessToken, 'refresh_token' => $tokens->refreshToken, 'expires_in' => $tokens->expiresIn]);
    }

    private function sendOneTimeEmail(MailerInterface $mailer, string $recipient, string $subject, string $purpose, string $token): void
    {
        $mailer->send((new Email())
            ->from('no-reply@betterauth-demo.test')
            ->to($recipient)
            ->subject($subject)
            ->text(sprintf('Use this %s token once: %s', $purpose, $token)));
    }

    private function problem(string $type, string $detail, int $status): JsonResponse
    {
        return new JsonResponse(['type' => 'https://example.test/problems/'.$type, 'title' => 'Request cannot be processed', 'status' => $status, 'detail' => $detail], $status, ['Content-Type' => 'application/problem+json']);
    }

    private function tooMany(): JsonResponse
    {
        return $this->problem('rate_limited', 'Too many attempts. Try again later.', 429);
    }
}
