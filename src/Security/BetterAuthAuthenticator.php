<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Security;

use BetterAuth\Symfony\Core\TokenService;
use BetterAuth\Symfony\Core\Exceptions\InvalidTokenException;
use BetterAuth\Symfony\Core\Exceptions\TokenExpiredException;
use BetterAuth\Symfony\TokenExtractor\TokenExtractorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class BetterAuthAuthenticator extends AbstractAuthenticator
{
    /** @param UserProviderInterface<UserInterface> $userProvider */
    public function __construct(
        private readonly TokenService $tokens,
        private readonly TokenExtractorInterface $extractor,
        private readonly UserProviderInterface $userProvider,
        private readonly string $userIdClaim = 'sub',
    ) {
    }

    public function supports(Request $request): bool
    {
        return $this->extractor->extract($request) !== null;
    }

    public function authenticate(Request $request): Passport
    {
        $rawToken = $this->extractor->extract($request);
        if ($rawToken === null) {
            throw new CustomUserMessageAuthenticationException('No access token provided.');
        }

        try {
            $claims = $this->tokens->parseAccessToken($rawToken);
            $identifier = $claims[$this->userIdClaim] ?? null;
            if (!is_string($identifier) || $identifier === '') {
                throw new InvalidTokenException('The user identifier claim is invalid.');
            }
        } catch (TokenExpiredException|InvalidTokenException $e) {
            throw new CustomUserMessageAuthenticationException('Invalid access token.', [], 0, $e);
        }

        return new SelfValidatingPassport(new UserBadge(
            $identifier,
            fn (string $userIdentifier): UserInterface => $this->userProvider->loadUserByIdentifier($userIdentifier),
        ));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, \Throwable $exception): Response
    {
        return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
    }
}
