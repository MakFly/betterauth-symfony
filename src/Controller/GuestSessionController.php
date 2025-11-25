<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Controller;

use BetterAuth\Core\AuthManager;
use BetterAuth\Providers\GuestSessionProvider\GuestSessionProvider;
use BetterAuth\Symfony\Exception\ValidationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles guest sessions: create, get, convert to user, delete.
 */
#[Route('/auth/guest', name: 'better_auth_guest_')]
class GuestSessionController extends AbstractController
{
    public function __construct(
        private readonly GuestSessionProvider $guestSessionProvider,
        private readonly AuthManager $authManager,
    ) {
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function createGuestSession(Request $request): JsonResponse
    {
        $data = $request->toArray();

        $guestSession = $this->guestSessionProvider->createGuestSession(
            $request->headers->get('User-Agent') ?? 'Unknown',
            $request->getClientIp() ?? '127.0.0.1',
            $data['metadata'] ?? null,
        );

        return $this->json([
            'guest_token' => $guestSession->token,
            'expires_at' => $guestSession->expiresAt,
            'created_at' => $guestSession->createdAt,
        ], 201);
    }

    #[Route('/{token}', name: 'get', methods: ['GET'])]
    public function getGuestSession(string $token): JsonResponse
    {
        $guestSession = $this->guestSessionProvider->getGuestSession($token);

        if ($guestSession === null) {
            throw new NotFoundHttpException('Guest session not found');
        }

        $expiresAt = new \DateTimeImmutable($guestSession->expiresAt);
        if ($expiresAt < new \DateTimeImmutable()) {
            throw new GoneHttpException('Guest session has expired');
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
    }

    #[Route('/convert', name: 'convert', methods: ['POST'])]
    public function convertToUser(Request $request): JsonResponse
    {
        $data = $request->toArray();

        $userData = [
            'email' => $data['email'],
            'name' => $data['name'] ?? null,
            'password_hash' => password_hash($data['password'], PASSWORD_ARGON2ID),
        ];

        $user = $this->guestSessionProvider->convertToUser($data['guestSessionId'], $userData);

        $tokens = $this->authManager->signUp(
            $data['email'],
            $data['password'],
            isset($data['name']) ? ['name' => $data['name']] : [],
        );

        return $this->json([
            'message' => 'Guest session converted to user successfully',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
            ],
            'access_token' => $tokens['access_token'] ?? null,
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'expires_in' => $tokens['expires_in'] ?? 3600,
            'token_type' => 'Bearer',
        ], 201);
    }

    #[Route('/{token}', name: 'delete', methods: ['DELETE'])]
    public function deleteGuestSession(string $token): JsonResponse
    {
        $guestSession = $this->guestSessionProvider->getGuestSession($token);

        if ($guestSession === null) {
            throw new NotFoundHttpException('Guest session not found');
        }

        $deleted = $this->guestSessionProvider->deleteGuestSession($guestSession->id);

        if (!$deleted) {
            throw new ValidationException('Failed to delete guest session');
        }

        return $this->json(['message' => 'Guest session deleted successfully']);
    }
}
