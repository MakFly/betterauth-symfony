<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Controller\Trait;

use BetterAuth\Core\Entities\User;

/**
 * Trait for formatting authentication responses.
 * Use this in your controllers to format user and auth responses consistently.
 */
trait AuthResponseTrait
{
    protected function formatUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'emailVerified' => $user->isEmailVerified(),
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $user->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $result
     */
    protected function formatAuthResponse(array $result, User $user): array
    {
        // Handle session mode
        if (isset($result['session']) && !isset($result['access_token'])) {
            $session = $result['session'];
            return [
                'access_token' => $session->getToken(),
                'refresh_token' => $session->getToken(),
                'expires_in' => 604800,
                'token_type' => 'Bearer',
                'user' => $this->formatUser($user),
            ];
        }

        // API mode
        return [
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'expires_in' => $result['expires_in'] ?? 3600,
            'token_type' => $result['token_type'] ?? 'Bearer',
            'user' => $this->formatUser($user),
        ];
    }

    protected function extractBearerToken(\Symfony\Component\HttpFoundation\Request $request): ?string
    {
        $authHeader = $request->headers->get('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }
        return $request->cookies->get('access_token');
    }
}
