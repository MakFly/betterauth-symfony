<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Controller;

use BetterAuth\Core\AuthManager;
use BetterAuth\Core\Entities\User;
use BetterAuth\Providers\TotpProvider\TotpProvider;
use BetterAuth\Symfony\Controller\Trait\AuthResponseTrait;
use BetterAuth\Symfony\Exception\AuthenticationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles credential-based authentication: register, login, and 2FA login.
 */
#[Route('/auth', name: 'better_auth_')]
class CredentialsController extends AbstractController
{
    use AuthResponseTrait;

    public function __construct(
        private readonly AuthManager $authManager,
        private readonly TotpProvider $totpProvider,
    ) {
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = $request->toArray();

        $additionalData = isset($data['name']) ? ['name' => $data['name']] : [];

        $user = $this->authManager->signUp(
            $data['email'],
            $data['password'],
            $additionalData
        );

        $result = $this->authManager->signIn(
            $data['email'],
            $data['password'],
            $request->getClientIp() ?? '127.0.0.1',
            $request->headers->get('User-Agent') ?? 'Unknown'
        );

        return $this->json($this->formatAuthResponse($result, $user), 201);
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = $request->toArray();

        $result = $this->authManager->signIn(
            $data['email'],
            $data['password'],
            $request->getClientIp() ?? '127.0.0.1',
            $request->headers->get('User-Agent') ?? 'Unknown'
        );

        $user = $result['user'];

        if ($this->totpProvider->requires2fa($user->getId())) {
            return $this->json([
                'requires2fa' => true,
                'message' => 'Two-factor authentication required',
                'user' => $this->formatUser($user),
            ]);
        }

        return $this->json($this->formatAuthResponse($result, $user));
    }

    #[Route('/login/2fa', name: 'login_2fa', methods: ['POST'])]
    public function login2fa(Request $request): JsonResponse
    {
        $data = $request->toArray();

        $result = $this->authManager->signIn(
            $data['email'],
            $data['password'],
            $request->getClientIp() ?? '127.0.0.1',
            $request->headers->get('User-Agent') ?? 'Unknown'
        );

        $user = $result['user'];

        $verified = $this->totpProvider->verify($user->getId(), $data['code']);
        if (!$verified) {
            if (isset($result['session'])) {
                $this->authManager->signOut($result['session']->getToken());
            } elseif (isset($result['access_token'])) {
                $this->authManager->revokeAllTokens($user->getId());
            }
            throw new AuthenticationException('Invalid 2FA code');
        }

        return $this->json($this->formatAuthResponse($result, $user));
    }
}
