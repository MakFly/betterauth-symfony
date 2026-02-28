<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Controller;

use BetterAuth\Core\AuthManager;
use BetterAuth\Providers\GuestSessionProvider\GuestSessionProvider;
use BetterAuth\Symfony\Controller\Trait\SafeErrorResponseTrait;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth/guest', name: 'better_auth_guest_')]
class GuestSessionController extends AbstractController
{
    use SafeErrorResponseTrait;

    public function __construct(
        private readonly GuestSessionProvider $guestSessionProvider,
        private readonly AuthManager $authManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function createGuestSession(Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();

            $guestSession = $this->guestSessionProvider->createGuestSession(
                $data['deviceInfo'] ?? $request->headers->get('User-Agent'),
                $request->getClientIp() ?? '127.0.0.1',
                $data['metadata'] ?? null,
            );

            $this->logger?->info('Guest session created', [
                'guestToken' => substr($guestSession->token, 0, 8) . '...',
                'ipAddress' => $request->getClientIp(),
            ]);

            return $this->json([
                'guest_token' => $guestSession->token,
                'expires_at' => $guestSession->expiresAt,
                'created_at' => $guestSession->createdAt,
            ], 201);
        } catch (\Exception $e) {
            return $this->safeError($e, 500, 'Failed to create guest session', 'createGuestSession');
        }
    }

    #[Route('/{token}', name: 'get', methods: ['GET'], priority: -10)]
    public function getGuestSession(string $token): JsonResponse
    {
        try {
            $guestSession = $this->guestSessionProvider->getGuestSession($token);

            if ($guestSession === null) {
                $this->logger?->debug('Guest session not found', [
                    'token' => substr($token, 0, 8) . '...',
                ]);
                return $this->json(['error' => 'Guest session not found'], 404);
            }

            $expiresAt = new \DateTimeImmutable($guestSession->expiresAt);
            if ($expiresAt < new \DateTimeImmutable()) {
                $this->logger?->debug('Guest session expired', [
                    'token' => substr($token, 0, 8) . '...',
                ]);
                return $this->json(['error' => 'Guest session has expired'], 410);
            }

            return $this->json([
                'id' => $guestSession->id,
                'token' => $guestSession->token,
                'device_info' => $guestSession->deviceInfo,
                'ip_address' => $guestSession->ipAddress,
                'created_at' => $guestSession->createdAt,
                'expires_at' => $guestSession->expiresAt,
                'metadata' => $guestSession->metadata,
            ]);
        } catch (\Exception $e) {
            return $this->safeError($e, 500, 'Failed to get guest session', 'getGuestSession');
        }
    }

    #[Route('/convert', name: 'convert', methods: ['POST'])]
    public function convertToUser(Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();

            if (!isset($data['guest_token'])) {
                return $this->json(['error' => 'guest_token is required'], 400);
            }

            if (!isset($data['email'])) {
                return $this->json(['error' => 'email is required'], 400);
            }

            $userData = [
                'email' => $data['email'],
                'name' => $data['name'] ?? null,
                'password' => $data['password'] ?? null, // Raw password — convertToUser handles hashing
            ];

            $user = $this->guestSessionProvider->convertToUser($data['guest_token'], $userData);

            $rawPassword = $data['password'] ?? bin2hex(random_bytes(16));
            $tokens = $this->authManager->signIn(
                $data['email'],
                $rawPassword,
                $request->getClientIp() ?? '127.0.0.1',
                $request->headers->get('User-Agent') ?? 'Unknown',
            );

            $this->logger?->info('Guest session converted to user', [
                'userId' => $user->getId(),
                'email' => $user->getEmail(),
            ]);

            return $this->json([
                'message' => 'Guest session converted to user successfully',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'username' => $user->getUsername(),
                ],
                'access_token' => $tokens['access_token'] ?? null,
                'refresh_token' => $tokens['refresh_token'] ?? null,
                'expires_in' => $tokens['expires_in'] ?? 3600,
                'token_type' => 'Bearer',
            ], 201);
        } catch (\RuntimeException $e) {
            return $this->safeError($e, 400, 'Guest session conversion failed', 'convertToUser');
        } catch (\Exception $e) {
            return $this->safeError($e, 500, 'Guest session conversion failed', 'convertToUser');
        }
    }

    #[Route('/{token}', name: 'delete', methods: ['DELETE'], priority: -10)]
    public function deleteGuestSession(string $token): JsonResponse
    {
        try {
            $guestSession = $this->guestSessionProvider->getGuestSession($token);

            if ($guestSession === null) {
                return $this->json(['error' => 'Guest session not found'], 404);
            }

            $deleted = $this->guestSessionProvider->deleteGuestSession($guestSession->id);

            if (!$deleted) {
                $this->logger?->error('Failed to delete guest session', [
                    'guestSessionId' => $guestSession->id,
                ]);
                return $this->json(['error' => 'Failed to delete guest session'], 500);
            }

            $this->logger?->info('Guest session deleted', [
                'guestSessionId' => $guestSession->id,
            ]);

            return $this->json(['message' => 'Guest session deleted successfully']);
        } catch (\Exception $e) {
            return $this->safeError($e, 500, 'Failed to delete guest session', 'deleteGuestSession');
        }
    }
}
