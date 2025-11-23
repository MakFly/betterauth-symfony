<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Security;

use BetterAuth\Core\Exceptions\InvalidTokenException;
use BetterAuth\Core\TokenAuthManager;
use BetterAuth\Symfony\Event\AuthenticationFailureEvent;
use BetterAuth\Symfony\Event\BetterAuthEvents;
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
 * Similaire à LexikJWTAuthenticationBundle mais pour BetterAuth
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
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    /**
     * Vérifie si cette requête nécessite une authentification
     */
    public function supports(Request $request): ?bool
    {
        // Authentifier si on a un header Authorization
        return $request->headers->has('Authorization');
    }

    /**
     * Authentifie la requête
     */
    public function authenticate(Request $request): Passport
    {
        $token = $this->extractToken($request);

        if (!$token) {
            throw new CustomUserMessageAuthenticationException('No API token provided');
        }

        try {
            // Vérifier le token via TokenAuthManager
            $user = $this->authManager->verify($token);

            // Créer le Passport avec l'utilisateur vérifié
            return new SelfValidatingPassport(
                new UserBadge(
                    $user->id,
                    function (string $userId) use ($user) {
                        // Retourner un BetterAuthUser wrappé
                        return new BetterAuthUser($user);
                    }
                )
            );
        } catch (InvalidTokenException $e) {
            throw new CustomUserMessageAuthenticationException('Invalid or expired token');
        }
    }

    /**
     * Appelé quand l'authentification réussit
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Pas de redirection, laisser la requête continuer
        return null;
    }

    /**
     * Appelé quand l'authentification échoue
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $response = new JsonResponse([
            'error' => 'Unauthorized',
            'message' => $exception->getMessage(),
        ], Response::HTTP_UNAUTHORIZED);

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
