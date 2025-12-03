<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Security;

use BetterAuth\Core\Exceptions\InvalidTokenException;
use BetterAuth\Core\Exceptions\TokenExpiredException;
use BetterAuth\Core\Interfaces\TokenAuthManagerInterface;
use BetterAuth\Core\Interfaces\TokenSignerInterface;
use BetterAuth\Symfony\Event\AuthenticationFailureEvent;
use BetterAuth\Symfony\Event\AuthenticationSuccessEvent;
use BetterAuth\Symfony\Event\BetterAuthEvents;
use BetterAuth\Symfony\Event\TokenAuthenticatedEvent;
use BetterAuth\Symfony\Event\TokenDecodedEvent;
use BetterAuth\Symfony\Event\TokenExpiredEvent;
use BetterAuth\Symfony\Event\TokenInvalidEvent;
use BetterAuth\Symfony\Event\TokenNotFoundEvent;
use BetterAuth\Symfony\Model\User as BetterAuthModelUser;
use BetterAuth\Symfony\TokenExtractor\AuthorizationHeaderTokenExtractor;
use BetterAuth\Symfony\TokenExtractor\TokenExtractorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Authenticator pour intégration avec security.yml
 *
 * Compatible avec le système d'événements de LexikJWTAuthenticationBundle:
 * - TOKEN_CREATED: before signing (via EventDispatchingTokenService)
 * - TOKEN_DECODED: after decoding, before validation
 * - TOKEN_AUTHENTICATED: after full validation
 * - TOKEN_INVALID: when token signature is invalid
 * - TOKEN_NOT_FOUND: when no token in request
 * - TOKEN_EXPIRED: when token has expired
 * - AUTHENTICATION_SUCCESS: on successful auth
 * - AUTHENTICATION_FAILURE: on failed auth
 *
 * Supports configurable token extractors (similar to Lexik):
 * - AuthorizationHeaderTokenExtractor (default)
 * - CookieTokenExtractor
 * - QueryParameterTokenExtractor
 * - ChainTokenExtractor (combines multiple)
 *
 * Returns the actual Doctrine User entity (App\Entity\User) via the UserProvider,
 * allowing $security->getUser() to return the real entity for seamless integration.
 *
 * Usage dans security.yaml:
 * security:
 *     firewalls:
 *         api:
 *             pattern: ^/api
 *             stateless: true
 *             custom_authenticators:
 *                 - BetterAuth\Symfony\Security\BetterAuthAuthenticator
 */
class BetterAuthAuthenticator extends AbstractAuthenticator
{
    private readonly TokenExtractorInterface $tokenExtractor;

    public function __construct(
        private readonly TokenAuthManagerInterface $authManager,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly UserProviderInterface $userProvider,
        private readonly ?TokenSignerInterface $tokenService = null,
        private readonly bool $debug = false,
        ?TokenExtractorInterface $tokenExtractor = null,
    ) {
        // Default to Authorization header extractor if none provided
        $this->tokenExtractor = $tokenExtractor ?? new AuthorizationHeaderTokenExtractor();
    }

    /**
     * Vérifie si cette requête nécessite une authentification
     */
    public function supports(Request $request): ?bool
    {
        // Check if any extractor can extract a token
        return $this->tokenExtractor->extract($request) !== null;
    }

    /**
     * Authentifie la requête
     */
    public function authenticate(Request $request): Passport
    {
        $token = $this->tokenExtractor->extract($request);

        // Event: TOKEN_NOT_FOUND
        if (!$token) {
            $event = new TokenNotFoundEvent('No token found in request');
            $this->dispatcher->dispatch($event, BetterAuthEvents::TOKEN_NOT_FOUND);

            if ($event->getResponse()) {
                throw new CustomUserMessageAuthenticationException(
                    'No API token provided',
                    [],
                    0,
                    new \RuntimeException('Custom response set')
                );
            }

            throw new CustomUserMessageAuthenticationException('No API token provided');
        }

        try {
            // Event: TOKEN_DECODED (before full validation)
            if ($this->tokenService) {
                $decodedPayload = $this->tokenService->decode($token);
                if ($decodedPayload) {
                    $decodedEvent = new TokenDecodedEvent($decodedPayload, $token);
                    $this->dispatcher->dispatch($decodedEvent, BetterAuthEvents::TOKEN_DECODED);

                    if (!$decodedEvent->isValid()) {
                        throw new InvalidTokenException('Token marked as invalid by listener');
                    }
                }
            }

            // Vérifier le token via TokenAuthManager (returns Core\Entities\User DTO)
            $coreUser = $this->authManager->verify($token);

            // Event: TOKEN_AUTHENTICATED
            $authenticatedEvent = new TokenAuthenticatedEvent(
                $this->tokenService?->decode($token) ?? [],
                $coreUser,
                $token
            );
            $this->dispatcher->dispatch($authenticatedEvent, BetterAuthEvents::TOKEN_AUTHENTICATED);

            // Create Passport using UserProvider to load the actual Doctrine entity
            // This ensures $security->getUser() returns App\Entity\User, not a wrapper
            return new SelfValidatingPassport(
                new UserBadge(
                    $coreUser->getId(),
                    fn (string $userId): UserInterface => $this->userProvider->loadUserByIdentifier($userId)
                )
            );

        } catch (TokenExpiredException $e) {
            // Event: TOKEN_EXPIRED
            $expiredEvent = new TokenExpiredEvent($token, $e->getExpiredAt());
            $this->dispatcher->dispatch($expiredEvent, BetterAuthEvents::TOKEN_EXPIRED);

            if ($expiredEvent->getResponse()) {
                throw new CustomUserMessageAuthenticationException(
                    'Token has expired',
                    [],
                    0,
                    $e
                );
            }

            throw new CustomUserMessageAuthenticationException('Token has expired');

        } catch (InvalidTokenException $e) {
            // Event: TOKEN_INVALID
            $invalidEvent = new TokenInvalidEvent(
                $this->debug ? $e->getMessage() : 'Invalid token',
                $token,
                $e
            );
            $this->dispatcher->dispatch($invalidEvent, BetterAuthEvents::TOKEN_INVALID);

            if ($invalidEvent->getResponse()) {
                throw new CustomUserMessageAuthenticationException(
                    'Invalid token',
                    [],
                    0,
                    $e
                );
            }

            throw new CustomUserMessageAuthenticationException('Invalid or expired token');
        }
    }

    /**
     * Appelé quand l'authentification réussit
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        // Dispatch success event if user is a BetterAuth model user
        if ($user instanceof BetterAuthModelUser) {
            // Event: AUTHENTICATION_SUCCESS
            $successEvent = new AuthenticationSuccessEvent($user);
            $this->dispatcher->dispatch($successEvent, BetterAuthEvents::AUTHENTICATION_SUCCESS);

            if ($successEvent->getResponse()) {
                return $successEvent->getResponse();
            }
        }

        // Pas de redirection, laisser la requête continuer
        return null;
    }

    /**
     * Appelé quand l'authentification échoue
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // Security: Ne pas exposer les détails d'erreur en production
        $message = $this->debug
            ? $exception->getMessage()
            : 'Authentication failed';

        $response = new JsonResponse([
            'error' => 'Unauthorized',
            'message' => $message,
        ], Response::HTTP_UNAUTHORIZED);

        // Event: AUTHENTICATION_FAILURE
        $event = new AuthenticationFailureEvent($exception, $response);
        $this->dispatcher->dispatch($event, BetterAuthEvents::AUTHENTICATION_FAILURE);

        return $event->getResponse();
    }

    /**
     * Get the token extractor.
     */
    public function getTokenExtractor(): TokenExtractorInterface
    {
        return $this->tokenExtractor;
    }
}
