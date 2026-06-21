<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Controller;

use BetterAuth\Core\AuthManager;
use BetterAuth\Symfony\Controller\Trait\AuthResponseTrait;
use BetterAuth\Symfony\Controller\Trait\SafeErrorResponseTrait;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth/sessions', name: 'better_auth_sessions_')]
class SessionController extends AbstractController
{
    use AuthResponseTrait;
    use SafeErrorResponseTrait;

    public function __construct(
        private readonly AuthManager $authManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            $token = $this->extractBearerToken($request);
            if (!$token) {
                return $this->json(['error' => 'No token provided'], 401);
            }

            $user = $this->authManager->getCurrentUser($token);
            if (!$user) {
                return $this->json(['error' => 'Invalid token'], 401);
            }

            $sessions = $this->authManager->getUserSessions((string) $user->getId());

            // Identify the current session by its opaque id (the token only matches the
            // current session, and at-rest hashing means listed tokens are not the plaintext).
            $currentId = $this->authManager->validateSession($token)->getId();

            $this->logger?->debug('Sessions listed', [
                'userId' => $user->getId(),
                'sessionCount' => count($sessions),
            ]);

            return $this->json([
                'sessions' => array_map(function ($session) use ($currentId) {
                    return [
                        'id' => $session->getId(),
                        'device' => $session->getMetadata()['device'] ?? 'Unknown',
                        'browser' => $session->getMetadata()['browser'] ?? 'Unknown',
                        'os' => $session->getMetadata()['os'] ?? 'Unknown',
                        'ip' => $session->getIpAddress(),
                        'location' => $session->getMetadata()['location'] ?? 'Unknown',
                        'current' => $session->getId() !== null && $session->getId() === $currentId,
                        'createdAt' => $session->getCreatedAt()->format('Y-m-d H:i:s'),
                        'lastActiveAt' => $session->getUpdatedAt()->format('Y-m-d H:i:s'),
                        'expiresAt' => $session->getExpiresAt()->format('Y-m-d H:i:s'),
                    ];
                }, $sessions),
            ]);
        } catch (\Exception $e) {
            return $this->safeError($e, 400, 'Failed to list sessions', 'list');
        }
    }

    /**
     * Revoke a session by its opaque id (not the secret token).
     */
    #[Route('/{sessionId}', name: 'revoke', methods: ['DELETE'])]
    public function revoke(string $sessionId, Request $request): JsonResponse
    {
        try {
            $token = $this->extractBearerToken($request);
            if (!$token) {
                return $this->json(['error' => 'No token provided'], 401);
            }

            $user = $this->authManager->getCurrentUser($token);
            if (!$user) {
                return $this->json(['error' => 'Invalid token'], 401);
            }

            $this->authManager->revokeSession((string) $user->getId(), $sessionId);

            $this->logger?->info('Session revoked', [
                'userId' => $user->getId(),
                'sessionId' => $sessionId,
            ]);

            return $this->json(['message' => 'Session revoked successfully']);
        } catch (\Exception $e) {
            return $this->safeError($e, 400, 'Failed to revoke session', 'revoke');
        }
    }
}
