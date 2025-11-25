<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Controller;

use BetterAuth\Core\AuthManager;
use BetterAuth\Core\Entities\User;
use BetterAuth\Symfony\Controller\Trait\AuthResponseTrait;
use BetterAuth\Symfony\Security\Attribute\CurrentUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles token operations: me, refresh, logout, revoke all.
 */
#[Route('/auth', name: 'better_auth_')]
class TokenController extends AbstractController
{
    use AuthResponseTrait;

    public function __construct(
        private readonly AuthManager $authManager,
    ) {
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->formatUser($user));
    }

    #[Route('/refresh', name: 'refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $result = $this->authManager->refresh($data['refreshToken']);
        return $this->json($result);
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $token = $this->extractBearerToken($request);
        if ($token) {
            $this->authManager->signOut($token);
        }

        return $this->json(['message' => 'Logged out successfully']);
    }

    #[Route('/revoke-all', name: 'revoke_all', methods: ['POST'])]
    public function revokeAll(#[CurrentUser] User $user): JsonResponse
    {
        $count = $this->authManager->revokeAllTokens($user->getId());

        return $this->json([
            'message' => 'All sessions revoked successfully',
            'count' => $count,
        ]);
    }
}
