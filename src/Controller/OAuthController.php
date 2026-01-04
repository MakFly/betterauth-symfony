<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Controller;

use BetterAuth\Core\AuthManager;
use BetterAuth\Providers\OAuthProvider\OAuthManager;
use BetterAuth\Providers\TotpProvider\TotpProvider;
use BetterAuth\Symfony\Controller\Trait\AuthResponseTrait;
use Psr\Cache\CacheItemPoolInterface;
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

    private const OAUTH_STATE_CACHE_PREFIX = 'better_auth.oauth_state.';
    private const OAUTH_STATE_TTL = 600;

    public function __construct(
        private readonly AuthManager $authManager,
        private readonly OAuthManager $oauthManager,
        private readonly TotpProvider $totpProvider,
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cache,
        private readonly ?LoggerInterface $logger = null,
        #[Autowire(env: 'FRONTEND_URL')]
        private readonly string $frontendUrl = 'http://localhost:5173',
    ) {
    }

    #[Route('/providers', name: 'providers', methods: ['GET'], priority: 10)]
    public function providers(): JsonResponse
    {
        try {
            $providers = $this->oauthManager->getAvailableProviders();
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
            $this->storeOAuthState($provider, $result['state']);

            // If redirect query param, redirect directly
            if ($request->query->get('redirect') === 'true') {
                return new RedirectResponse($result['url']);
            }

            return $this->json([
                'url' => $result['url'],
                'state' => $result['state'],
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
            $this->storeOAuthState($provider, $result['state']);
            return $this->json([
                'url' => $result['url'],
                'state' => $result['state'],
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

        $state = $request->query->get('state');
        if (!$state || !$this->consumeOAuthState($provider, $state)) {
            $this->logger?->warning('OAuth callback rejected due to invalid state', [
                'provider' => $provider,
                'has_state' => $state !== null,
            ]);

            return $this->redirectToFrontend(['error' => 'Invalid OAuth state']);
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

            // $result['user'] is already a DTO array (password excluded)
            $userData = $result['user'];
            $session = $result['session'] ?? null;
            $userId = $userData['id'];
            $userEmail = $userData['email'];

            // Check 2FA
            if ($userId && $this->totpProvider->requires2fa($userId)) {
                if ($session) {
                    $this->authManager->signOut($session->getToken());
                }

                return $this->redirectToFrontend([
                    'requires2fa' => 'true',
                    'email' => $userEmail,
                ]);
            }

            // Build redirect params using session token or access token
            if ($session) {
                $params = [
                    'access_token' => $session->getToken(),
                    'refresh_token' => $session->getToken(),
                    'expires_in' => '604800',
                ];
            } else {
                // API mode - use tokens from result
                $params = [
                    'access_token' => $result['access_token'] ?? '',
                    'refresh_token' => $result['refresh_token'] ?? '',
                    'expires_in' => (string)($result['expires_in'] ?? 3600),
                ];
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

    private function storeOAuthState(string $provider, string $state): void
    {
        $cacheKey = self::OAUTH_STATE_CACHE_PREFIX . hash('sha256', $state);
        $item = $this->cache->getItem($cacheKey);
        $item->set([
            'provider' => $provider,
            'created_at' => time(),
        ]);
        $item->expiresAfter(self::OAUTH_STATE_TTL);
        $this->cache->save($item);
    }

    private function consumeOAuthState(string $provider, string $state): bool
    {
        $cacheKey = self::OAUTH_STATE_CACHE_PREFIX . hash('sha256', $state);
        $item = $this->cache->getItem($cacheKey);
        if (!$item->isHit()) {
            return false;
        }

        $data = $item->get();
        $this->cache->deleteItem($cacheKey);

        return is_array($data) && ($data['provider'] ?? null) === $provider;
    }
}
