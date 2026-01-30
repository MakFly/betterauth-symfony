<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Controller;

use BetterAuth\Core\AuthManager;
use BetterAuth\Providers\TotpProvider\TotpProvider;
use BetterAuth\Symfony\Controller\Trait\AuthResponseTrait;
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

    public function __construct(
        private readonly AuthManager $authManager,
        private readonly TotpProvider $totpProvider,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(#[MapRequestPayload] RegisterRequestDto $dto): JsonResponse
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
                '127.0.0.1', // IP will be set in middleware
                'Unknown'   // User agent will be set in middleware
            );

            return $this->json($result, 201);
        } catch (\Exception $e) {
            $this->logger?->error('Registration failed', [
                'email' => $dto->email,
                'error' => $e->getMessage(),
            ]);
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(#[MapRequestPayload] LoginRequestDto $dto, Request $request): JsonResponse
    {
        try {
            $result = $this->authManager->signIn(
                $dto->email,
                $dto->password,
                $request->getClientIp() ?? '127.0.0.1',
                $request->headers->get('User-Agent') ?? 'Unknown'
            );

            $userData = $result['user'];
            $userId = $userData['id'];

            if ($this->totpProvider->requires2fa($userId)) {
                return $this->json([
                    'requires2fa' => true,
                    'message' => 'Two-factor authentication required',
                    'user' => $userData,
                ]);
            }

            return $this->json($result);
        } catch (\Exception $e) {
            $this->logger?->error('Login failed', [
                'email' => $dto->email,
                'error' => $e->getMessage(),
            ]);
            return $this->json(['error' => $e->getMessage()], 401);
        }
    }

    #[Route('/login/2fa', name: 'login_2fa', methods: ['POST'])]
    public function login2fa(#[MapRequestPayload] Login2faRequestDto $dto, Request $request): JsonResponse
    {
        try {
            $result = $this->authManager->signIn(
                $dto->email,
                $dto->password,
                $request->getClientIp() ?? '127.0.0.1',
                $request->headers->get('User-Agent') ?? 'Unknown'
            );

            $userData = $result['user'];
            $userId = $userData['id'];

            $verified = $this->totpProvider->verify($userId, $dto->code);
            if (!$verified) {
                if (isset($result['session'])) {
                    $this->authManager->signOut($result['session']->getToken());
                } elseif (isset($result['access_token'])) {
                    $this->authManager->revokeAllTokens($userId);
                }
                return $this->json(['error' => 'Invalid 2FA code'], 401);
            }

            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 401);
        }
    }
}
