<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Controller;

use BetterAuth\Core\AuthManager;
use BetterAuth\Core\Interfaces\RateLimiterInterface;
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

    // Guest-session creation is unauthenticated; cap it per IP to prevent
    // resource exhaustion / table pollution (SEC-24).
    private const CREATE_MAX_ATTEMPTS = 20;
    private const CREATE_DECAY_SECONDS = 60;

    public function __construct(
        private readonly GuestSessionProvider $guestSessionProvider,
        private readonly AuthManager $authManager,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?RateLimiterInterface $rateLimiter = null,
    ) {
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function createGuestSession(Request $request): JsonResponse
    {
        $rateKey = 'guest_create:' . ($request->getClientIp() ?? 'unknown');
        if ($this->rateLimiter !== null
            && $this->rateLimiter->tooManyAttempts($rateKey, self::CREATE_MAX_ATTEMPTS, self::CREATE_DECAY_SECONDS)
        ) {
            return $this->json([
                'error' => 'Too many guest sessions created. Please try again later.',
                'retryAfter' => $this->rateLimiter->availableIn($rateKey),
            ], 429);
        }
        $this->rateLimiter?->hit($rateKey, self::CREATE_DECAY_SECONDS);

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

            if (isset($data['password'])) {
                // Password provided: authenticate normally.
                $tokens = $this->authManager->signIn(
                    $data['email'],
                    $data['password'],
                    $request->getClientIp() ?? '127.0.0.1',
                    $request->headers->get('User-Agent') ?? 'Unknown',
                );
            } elseif ($this->authManager->supportsTokens()) {
                // Password-less conversion: the guest_token already authenticated the
                // intent, so issue tokens directly for the freshly converted user
                // instead of attempting a sign-in that can never succeed.
                $tokens = $this->authManager->token()->createTokensForUser($user);
            } else {
                return $this->json(['error' => 'password is required to convert a guest session'], 400);
            }

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
