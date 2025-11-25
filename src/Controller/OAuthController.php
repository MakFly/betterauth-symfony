<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Controller;

use BetterAuth\Core\AuthManager;
use BetterAuth\Providers\OAuthProvider\OAuthManager;
use BetterAuth\Providers\TotpProvider\TotpProvider;
use BetterAuth\Symfony\Controller\Trait\AuthResponseTrait;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles OAuth authentication: providers list, redirect, callback.
 */
#[Route('/auth/oauth', name: 'better_auth_oauth_')]
class OAuthController extends AbstractController
{
    use AuthResponseTrait;

    public function __construct(
        private readonly AuthManager $authManager,
        private readonly OAuthManager $oauthManager,
        private readonly TotpProvider $totpProvider,
        private readonly ?LoggerInterface $logger = null,
        #[Autowire(env: 'FRONTEND_URL')]
        private readonly string $frontendUrl = 'http://localhost:5173',
    ) {
    }

    #[Route('/providers', name: 'providers', methods: ['GET'], priority: 10)]
    public function providers(): JsonResponse
    {
        $providers = $this->oauthManager->getAvailableProviders();

        return $this->json([
            'providers' => $providers,
            'count' => count($providers),
        ]);
    }

    #[Route('/{provider}', name: 'redirect', methods: ['GET'])]
    public function redirectToProvider(string $provider, Request $request): Response
    {
        $result = $this->oauthManager->getAuthorizationUrl($provider);

        if ($request->headers->get('Accept') === 'application/json') {
            return $this->json([
                'url' => $result['url'],
                'state' => $result['state'],
            ]);
        }

        return parent::redirect($result['url']);
    }

    #[Route('/{provider}/url', name: 'url', methods: ['GET'])]
    public function url(string $provider): JsonResponse
    {
        $result = $this->oauthManager->getAuthorizationUrl($provider);

        return $this->json([
            'url' => $result['url'],
            'state' => $result['state'],
            'provider' => $provider,
        ]);
    }

    #[Route('/{provider}/callback', name: 'callback', methods: ['GET'])]
    public function callback(string $provider, Request $request): Response
    {
        $code = $request->query->get('code');
        $error = $request->query->get('error');

        if ($error) {
            $errorDesc = $request->query->get('error_description', 'OAuth authentication failed');
            return parent::redirect($this->frontendUrl . '/login?error=' . urlencode($errorDesc));
        }

        if (!$code) {
            return parent::redirect($this->frontendUrl . '/login?error=' . urlencode('Authorization code is required'));
        }

        $redirectUri = $request->getSchemeAndHttpHost() . $request->getPathInfo();

        $this->logger?->info('OAuth callback started', [
            'provider' => $provider,
            'redirect_uri' => $redirectUri,
        ]);

        $result = $this->oauthManager->handleCallback(
            providerName: $provider,
            code: $code,
            redirectUri: $redirectUri,
            ipAddress: $request->getClientIp() ?? '127.0.0.1',
            userAgent: $request->headers->get('User-Agent') ?? 'Unknown'
        );

        $user = $result['user'];
        $session = $result['session'];
        $isNewUser = $result['isNewUser'];

        // Check if 2FA is required
        if ($this->totpProvider->requires2fa($user->getId())) {
            $this->authManager->signOut($session->getToken());
            return parent::redirect(
                $this->frontendUrl . '/2fa/validate?provider=' . $provider . '&email=' . urlencode($user->getEmail())
            );
        }

        // Generate tokens based on auth mode
        if ($this->authManager->isApiMode()) {
            $tokens = $this->authManager->token()->createTokensForUser($user);
            $accessToken = $tokens['access_token'];
            $refreshToken = $tokens['refresh_token'];

            $this->authManager->session()->signOut($session->getToken());
        } else {
            $accessToken = $session->getToken();
            $refreshToken = $session->getToken();
        }

        $params = http_build_query([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'provider' => $provider,
            'new_user' => $isNewUser ? '1' : '0',
        ]);

        return parent::redirect($this->frontendUrl . '/oauth/callback?' . $params);
    }
}
