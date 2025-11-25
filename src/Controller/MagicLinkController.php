<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Controller;

use BetterAuth\Core\Exceptions\RateLimitException;
use BetterAuth\Providers\MagicLinkProvider\MagicLinkProvider;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;

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
        try {
            $data = $request->toArray();

            if (!isset($data['email'])) {
                return $this->json(['error' => 'Email is required'], 400);
            }

            $callbackUrl = $data['callbackUrl'] ?? rtrim($this->frontendUrl, '/') . '/auth/magic-link/verify';

            $result = $this->magicLinkProvider->sendMagicLink(
                $data['email'],
                $request->getClientIp() ?? '127.0.0.1',
                $request->headers->get('User-Agent') ?? 'Unknown',
                $callbackUrl
            );

            return $this->json([
                'message' => 'Magic link sent successfully',
                'expiresIn' => $result['expiresIn'] ?? 900,
            ]);
        } catch (RateLimitException $e) {
            return $this->json([
                'error' => 'Too many requests. Please try again later.',
                'retryAfter' => $e->getRetryAfter(),
            ], 429);
        } catch (TransportExceptionInterface $e) {
            $this->logger?->error('Mailer error', ['error' => $e->getMessage()]);
            return $this->json([
                'error' => 'Failed to send email. Please check mailer configuration.',
            ], 500);
        } catch (\Exception $e) {
            $this->logger?->error('Magic link error', ['error' => $e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/verify', name: 'verify', methods: ['POST'])]
    public function verifyMagicLink(Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();

            if (!isset($data['token'])) {
                return $this->json(['error' => 'Magic link token is required'], 400);
            }

            $result = $this->magicLinkProvider->verifyMagicLink(
                $data['token'],
                $request->getClientIp() ?? '127.0.0.1',
                $request->headers->get('User-Agent') ?? 'Unknown'
            );

            if (!$result['success']) {
                return $this->json([
                    'error' => $result['error'] ?? 'Invalid or expired magic link',
                ], 400);
            }

            return $this->json([
                'access_token' => $result['access_token'],
                'refresh_token' => $result['refresh_token'],
                'expires_in' => $result['expires_in'],
                'token_type' => 'Bearer',
                'user' => $result['user'],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/verify/{token}', name: 'verify_get', methods: ['GET'])]
    public function verifyMagicLinkGet(string $token, Request $request): JsonResponse
    {
        try {
            $result = $this->magicLinkProvider->verifyMagicLink(
                $token,
                $request->getClientIp() ?? '127.0.0.1',
                $request->headers->get('User-Agent') ?? 'Unknown'
            );

            if (!$result['success']) {
                return $this->json([
                    'error' => $result['error'] ?? 'Invalid or expired magic link',
                ], 400);
            }

            return $this->json([
                'access_token' => $result['access_token'],
                'refresh_token' => $result['refresh_token'],
                'expires_in' => $result['expires_in'],
                'token_type' => 'Bearer',
                'user' => $result['user'],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
