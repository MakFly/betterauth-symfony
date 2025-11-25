<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Security\ValueResolver;

use BetterAuth\Core\AuthManager;
use BetterAuth\Core\Entities\User;
use BetterAuth\Symfony\Exception\AuthenticationException;
use BetterAuth\Symfony\Security\Attribute\CurrentUser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * Resolves controller parameters annotated with #[CurrentUser] attribute.
 *
 * This resolver automatically extracts the Bearer token from the request,
 * validates it, and injects the authenticated User entity into the controller.
 *
 * Supports both:
 * - Bearer token in Authorization header (API mode)
 * - access_token cookie (Session/Hybrid mode)
 */
class CurrentUserResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly AuthManager $authManager,
    ) {
    }

    /**
     * @return iterable<User|null>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        // Only resolve for User type
        if ($argument->getType() !== User::class) {
            return [];
        }

        // Only resolve if #[CurrentUser] attribute is present
        $attributes = $argument->getAttributes(CurrentUser::class);
        if (empty($attributes)) {
            return [];
        }

        /** @var CurrentUser $attr */
        $attr = $attributes[0];
        $token = $this->extractToken($request);

        if (!$token) {
            if ($attr->optional) {
                yield null;
                return;
            }
            throw new AuthenticationException('No token provided');
        }

        $user = $this->authManager->getCurrentUser($token);

        if (!$user) {
            if ($attr->optional) {
                yield null;
                return;
            }
            throw new AuthenticationException('Invalid or expired token');
        }

        yield $user;
    }

    /**
     * Extract authentication token from request.
     *
     * Checks in order:
     * 1. Authorization: Bearer <token> header
     * 2. access_token cookie
     */
    private function extractToken(Request $request): ?string
    {
        // 1. Try Bearer token (API mode)
        $authHeader = $request->headers->get('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // 2. Try access_token cookie (Session/Hybrid mode)
        return $request->cookies->get('access_token');
    }
}
