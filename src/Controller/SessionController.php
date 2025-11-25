<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Controller;

use BetterAuth\Core\AuthManager;
use BetterAuth\Core\Entities\User;
use BetterAuth\Symfony\Security\Attribute\CurrentUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles session management: list sessions, revoke specific session.
 */
#[Route('/auth/sessions', name: 'better_auth_sessions_')]
class SessionController extends AbstractController
{
    public function __construct(
        private readonly AuthManager $authManager,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        $sessions = $this->authManager->getUserSessions($user->getId());

        return $this->json([
            'sessions' => array_map(fn ($session) => [
                'id' => $session->getToken(),
                'device' => $session->getMetadata()['device'] ?? 'Unknown',
                'browser' => $session->getMetadata()['browser'] ?? 'Unknown',
                'os' => $session->getMetadata()['os'] ?? 'Unknown',
                'ip' => $session->getIpAddress(),
                'location' => $session->getMetadata()['location'] ?? 'Unknown',
                'current' => $session->getMetadata()['isCurrent'] ?? false,
                'createdAt' => $session->getCreatedAt()->format('Y-m-d H:i:s'),
                'lastActiveAt' => $session->getUpdatedAt()->format('Y-m-d H:i:s'),
                'expiresAt' => $session->getExpiresAt()->format('Y-m-d H:i:s'),
            ], $sessions),
        ]);
    }

    #[Route('/{sessionId}', name: 'revoke', methods: ['DELETE'])]
    public function revoke(string $sessionId, #[CurrentUser] User $user): JsonResponse
    {
        $this->authManager->revokeSession($user->getId(), $sessionId);

        return $this->json(['message' => 'Session revoked successfully']);
    }
}
