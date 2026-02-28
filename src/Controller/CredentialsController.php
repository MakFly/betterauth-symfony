<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Controller;

use BetterAuth\Core\AuthManager;
use BetterAuth\Core\Interfaces\UserRepositoryInterface;
use BetterAuth\Core\PasswordHasher;
use BetterAuth\Providers\TotpProvider\TotpProvider;
use BetterAuth\Symfony\Controller\Trait\AuthResponseTrait;
use BetterAuth\Symfony\Controller\Trait\SafeErrorResponseTrait;
use BetterAuth\Symfony\Dto\Login2faRequestDto;
use BetterAuth\Symfony\Dto\LoginRequestDto;
use BetterAuth\Symfony\Dto\RegisterRequestDto;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth', name: 'better_auth_')]
class CredentialsController extends AbstractController
{
    use AuthResponseTrait;
    use SafeErrorResponseTrait;

    public function __construct(
        private readonly AuthManager $authManager,
        private readonly TotpProvider $totpProvider,
        private readonly UserRepositoryInterface $userRepository,
        private readonly PasswordHasher $passwordHasher,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(#[MapRequestPayload] RegisterRequestDto $dto, Request $request): JsonResponse
    {
        try {
            $additionalData = $dto->name !== null ? ['name' => $dto->name] : [];

            $user = $this->authManager->signUp(
                $dto->email,
                $dto->password,
                $additionalData
            );

            $result = $this->authManager->signIn(
                $dto->email,
                $dto->password,
                $request->getClientIp() ?? '127.0.0.1',
                $request->headers->get('User-Agent') ?? 'Unknown'
            );

            return $this->json($result, 201);
        } catch (\Exception $e) {
            $this->logger?->error('Registration failed', [
                'email' => $dto->email,
                'error' => $e->getMessage(),
            ]);
            return $this->safeError($e, 400, 'Registration failed', 'register');
        }
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(#[MapRequestPayload] LoginRequestDto $dto, Request $request): JsonResponse
    {
        try {
            // Step 1: Verify credentials WITHOUT creating any session or token
            $user = $this->userRepository->findByEmail($dto->email);
            if ($user === null || !$user->hasPassword()) {
                return $this->json(['error' => 'Invalid credentials'], 401);
            }

            $passwordHash = $user->getPassword();
            if ($passwordHash === null || !$this->passwordHasher->verify($dto->password, $passwordHash)) {
                return $this->json(['error' => 'Invalid credentials'], 401);
            }

            // Step 2: Check 2FA BEFORE creating any session or tokens
            if ($this->totpProvider->requires2fa((string) $user->getId())) {
                return $this->json([
                    'requires2fa' => true,
                    'message' => 'Two-factor authentication required',
                ]);
            }

            // Step 3: No 2FA required — proceed with normal sign-in to create session/tokens
            $result = $this->authManager->signIn(
                $dto->email,
                $dto->password,
                $request->getClientIp() ?? '127.0.0.1',
                $request->headers->get('User-Agent') ?? 'Unknown'
            );

            return $this->json($result);
        } catch (\Exception $e) {
            $this->logger?->error('Login failed', [
                'email' => $dto->email,
                'error' => $e->getMessage(),
            ]);
            return $this->json(['error' => 'Authentication failed'], 401);
        }
    }

    #[Route('/login/2fa', name: 'login_2fa', methods: ['POST'])]
    public function login2fa(#[MapRequestPayload] Login2faRequestDto $dto, Request $request): JsonResponse
    {
        try {
            // Step 1: Verify credentials WITHOUT creating any session or token
            $user = $this->userRepository->findByEmail($dto->email);
            if ($user === null || !$user->hasPassword()) {
                return $this->json(['error' => 'Invalid credentials'], 401);
            }

            $passwordHash = $user->getPassword();
            if ($passwordHash === null || !$this->passwordHasher->verify($dto->password, $passwordHash)) {
                return $this->json(['error' => 'Invalid credentials'], 401);
            }

            // Step 2: Verify TOTP code before creating any session or tokens
            $verified = $this->totpProvider->verify((string) $user->getId(), $dto->code);
            if (!$verified) {
                return $this->json(['error' => 'Invalid 2FA code'], 401);
            }

            // Step 3: Credentials and TOTP are valid — NOW create session/tokens
            $result = $this->authManager->signIn(
                $dto->email,
                $dto->password,
                $request->getClientIp() ?? '127.0.0.1',
                $request->headers->get('User-Agent') ?? 'Unknown'
            );

            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Authentication failed'], 401);
        }
    }
}
