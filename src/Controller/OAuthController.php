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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
        try {
            $providers = $this->oauthManager->getEnabledProviders();
            return $this->json(['providers' => $providers]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{provider}', name: 'redirect', methods: ['GET'])]
    public function redirectToProvider(string $provider, Request $request): Response
    {
        try {
            $result = $this->oauthManager->getAuthorizationUrl($provider);

            // If redirect query param, redirect directly
            if ($request->query->get('redirect') === 'true') {
                return new RedirectResponse($result['url']);
            }

            return $this->json([
                'url' => $result['url'],
                'state' => $result['state'] ?? null,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{provider}/url', name: 'url', methods: ['GET'])]
    public function url(string $provider): JsonResponse
    {
        try {
            $result = $this->oauthManager->getAuthorizationUrl($provider);
            return $this->json([
                'url' => $result['url'],
                'state' => $result['state'] ?? null,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{provider}/callback', name: 'callback', methods: ['GET'])]
    public function callback(string $provider, Request $request): Response
    {
        $code = $request->query->get('code');
        if (!$code) {
            return $this->redirectToFrontend(['error' => 'Authorization code is required']);
        }

        try {
            $redirectUri = $request->getSchemeAndHttpHost() . $request->getPathInfo();

            $result = $this->oauthManager->handleCallback(
                providerName: $provider,
                code: $code,
                redirectUri: $redirectUri,
                ipAddress: $request->getClientIp() ?? '127.0.0.1',
                userAgent: $request->headers->get('User-Agent') ?? 'Unknown'
            );

            // Check 2FA
            if (isset($result['user'])) {
                $user = $result['user'];
                $userId = $user->getId();

                if ($userId && $this->totpProvider->requires2fa($userId)) {
                    if (isset($result['session'])) {
                        $this->authManager->signOut($result['session']->getToken());
                    }

                    return $this->redirectToFrontend([
                        'requires2fa' => 'true',
                        'email' => $user->getEmail(),
                    ]);
                }
            }

            // Build redirect params
            $params = [];
            if (isset($result['access_token'])) {
                $params['access_token'] = $result['access_token'];
                $params['refresh_token'] = $result['refresh_token'] ?? '';
                $params['expires_in'] = (string) ($result['expires_in'] ?? 3600);
            } elseif (isset($result['session'])) {
                $params['access_token'] = $result['session']->getToken();
                $params['refresh_token'] = $result['session']->getToken();
                $params['expires_in'] = '604800';
            }

            return $this->redirectToFrontend($params, '/oauth/callback');
        } catch (\Exception $e) {
            $this->logger?->error('OAuth callback failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
            return $this->redirectToFrontend(['error' => $e->getMessage()]);
        }
    }

    private function redirectToFrontend(array $params, string $path = '/login'): RedirectResponse
    {
        $url = rtrim($this->frontendUrl, '/') . $path . '?' . http_build_query($params);
        return new RedirectResponse($url);
    }
}
