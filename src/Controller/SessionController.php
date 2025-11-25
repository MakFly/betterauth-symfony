<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Controller;

use BetterAuth\Core\AuthManager;
use BetterAuth\Symfony\Controller\Trait\AuthResponseTrait;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth/sessions', name: 'better_auth_sessions_')]
class SessionController extends AbstractController
{
    use AuthResponseTrait;

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

            $sessions = $this->authManager->getUserSessions($user->getId());

            $this->logger?->debug('Sessions listed', [
                'userId' => $user->getId(),
                'sessionCount' => count($sessions),
            ]);

            return $this->json([
                'sessions' => array_map(function ($session) use ($token) {
                    return [
                        'id' => $session->getToken(),
                        'device' => $session->getMetadata()['device'] ?? 'Unknown',
                        'browser' => $session->getMetadata()['browser'] ?? 'Unknown',
                        'os' => $session->getMetadata()['os'] ?? 'Unknown',
                        'ip' => $session->getIpAddress(),
                        'location' => $session->getMetadata()['location'] ?? 'Unknown',
                        'current' => $session->getToken() === $token,
                        'createdAt' => $session->getCreatedAt()->format('Y-m-d H:i:s'),
                        'lastActiveAt' => $session->getUpdatedAt()->format('Y-m-d H:i:s'),
                        'expiresAt' => $session->getExpiresAt()->format('Y-m-d H:i:s'),
                    ];
                }, $sessions),
            ]);
        } catch (\Exception $e) {
            $this->logger?->error('Failed to list sessions', [
                'error' => $e->getMessage(),
            ]);
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

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

            $this->authManager->revokeSession($user->getId(), $sessionId);

            $this->logger?->info('Session revoked', [
                'userId' => $user->getId(),
                'sessionId' => $sessionId,
            ]);

            return $this->json(['message' => 'Session revoked successfully']);
        } catch (\Exception $e) {
            $this->logger?->error('Failed to revoke session', [
                'sessionId' => $sessionId,
                'error' => $e->getMessage(),
            ]);
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
