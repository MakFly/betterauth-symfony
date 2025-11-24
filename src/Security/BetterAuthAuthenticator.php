<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Security;

use BetterAuth\Core\Exceptions\InvalidTokenException;
use BetterAuth\Core\Exceptions\TokenExpiredException;
use BetterAuth\Core\TokenAuthManager;
use BetterAuth\Core\TokenService;
use BetterAuth\Symfony\Event\AuthenticationFailureEvent;
use BetterAuth\Symfony\Event\AuthenticationSuccessEvent;
use BetterAuth\Symfony\Event\BetterAuthEvents;
use BetterAuth\Symfony\Event\TokenAuthenticatedEvent;
use BetterAuth\Symfony\Event\TokenDecodedEvent;
use BetterAuth\Symfony\Event\TokenExpiredEvent;
use BetterAuth\Symfony\Event\TokenInvalidEvent;
use BetterAuth\Symfony\Event\TokenNotFoundEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
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
    public function __construct(
        private readonly TokenAuthManager $authManager,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ?TokenService $tokenService = null,
        private readonly bool $debug = false,
    ) {
    }

    /**
     * Vérifie si cette requête nécessite une authentification
     */
    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization');
    }

    /**
     * Authentifie la requête
     */
    public function authenticate(Request $request): Passport
    {
        $token = $this->extractToken($request);

        // Event: TOKEN_NOT_FOUND
        if (!$token) {
            $event = new TokenNotFoundEvent('No Bearer token in Authorization header');
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

            // Vérifier le token via TokenAuthManager
            $user = $this->authManager->verify($token);

            // Event: TOKEN_AUTHENTICATED
            $authenticatedEvent = new TokenAuthenticatedEvent(
                $this->tokenService?->decode($token) ?? [],
                $user,
                $token
            );
            $this->dispatcher->dispatch($authenticatedEvent, BetterAuthEvents::TOKEN_AUTHENTICATED);

            // Créer le Passport avec l'utilisateur vérifié
            return new SelfValidatingPassport(
                new UserBadge(
                    $user->getId(),
                    function (string $userId) use ($user) {
                        return new BetterAuthUser($user);
                    }
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
        /** @var BetterAuthUser $user */
        $user = $token->getUser();

        if ($user instanceof BetterAuthUser) {
            // Event: AUTHENTICATION_SUCCESS
            $successEvent = new AuthenticationSuccessEvent($user->getBetterAuthUser());
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
     * Extraire le token Bearer depuis Authorization header
     */
    private function extractToken(Request $request): ?string
    {
        $header = $request->headers->get('Authorization');

        if (!$header) {
            return null;
        }

        // Format: "Bearer v4.local.xxxxx"
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
