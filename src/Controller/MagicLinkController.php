<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Controller;

use BetterAuth\Core\Exceptions\RateLimitException;
use BetterAuth\Providers\MagicLinkProvider\MagicLinkProvider;
use BetterAuth\Symfony\Exception\ValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles passwordless authentication via magic links.
 */
#[Route('/auth/magic-link', name: 'better_auth_magic_link_')]
class MagicLinkController extends AbstractController
{
    public function __construct(
        private readonly MagicLinkProvider $magicLinkProvider,
        private readonly ?LoggerInterface $logger = null,
        #[Autowire(env: 'FRONTEND_URL')]
        private readonly string $frontendUrl = 'http://localhost:5173',
    ) {
    }

    #[Route('/send', name: 'send', methods: ['POST'])]
    public function sendMagicLink(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $callbackUrl = $data['callbackUrl'] ?? rtrim($this->frontendUrl, '/') . '/auth/magic-link/verify';

        $this->logger?->info('Sending magic link', [
            'email' => $data['email'],
            'callbackUrl' => $callbackUrl,
        ]);

        try {
            $result = $this->magicLinkProvider->sendMagicLink(
                $data['email'],
                $request->getClientIp() ?? '127.0.0.1',
                $request->headers->get('User-Agent') ?? 'Unknown',
                $callbackUrl
            );

            return $this->json([
                'message' => 'Magic link sent successfully',
                'expiresIn' => $result['expiresIn'],
            ]);
        } catch (RateLimitException $e) {
            throw new TooManyRequestsHttpException(
                $e->retryAfter,
                'Too many requests. Please try again later.'
            );
        }
    }

    #[Route('/verify', name: 'verify', methods: ['POST'])]
    public function verifyMagicLink(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $result = $this->magicLinkProvider->verifyMagicLink(
            $data['token'],
            $request->getClientIp() ?? '127.0.0.1',
            $request->headers->get('User-Agent') ?? 'Unknown'
        );

        if (!$result['success']) {
            throw new ValidationException($result['error'] ?? 'Invalid or expired magic link');
        }

        return $this->json([
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'expires_in' => $result['expires_in'],
            'token_type' => 'Bearer',
            'user' => $result['user'],
        ]);
    }

    #[Route('/verify/{token}', name: 'verify_get', methods: ['GET'])]
    public function verifyMagicLinkGet(string $token, Request $request): JsonResponse
    {
        $result = $this->magicLinkProvider->verifyMagicLink(
            $token,
            $request->getClientIp() ?? '127.0.0.1',
            $request->headers->get('User-Agent') ?? 'Unknown'
        );

        if (!$result['success']) {
            throw new ValidationException($result['error'] ?? 'Invalid or expired magic link');
        }

        return $this->json([
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'expires_in' => $result['expires_in'],
            'token_type' => 'Bearer',
            'user' => $result['user'],
        ]);
    }
}
