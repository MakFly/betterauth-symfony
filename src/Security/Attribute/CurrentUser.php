<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Security\Attribute;

use Attribute;

/**
 * Parameter attribute to inject the currently authenticated user.
 *
 * Usage in controllers:
 * ```php
 * use BetterAuth\Core\Entities\User;
 * use BetterAuth\Symfony\Security\Attribute\CurrentUser;
 *
 * #[Route('/me', methods: ['GET'])]
 * public function me(#[CurrentUser] User $user): JsonResponse
 * {
 *     return $this->json($user);
 * }
 *
 * // Optional user (nullable)
 * #[Route('/profile', methods: ['GET'])]
 * public function profile(#[CurrentUser(optional: true)] ?User $user): JsonResponse
 * {
 *     return $this->json(['authenticated' => $user !== null]);
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class CurrentUser
{
    /**
     * @param bool $optional If true, returns null instead of throwing exception when not authenticated
     */
    public function __construct(
        public readonly bool $optional = false,
    ) {
    }
}
