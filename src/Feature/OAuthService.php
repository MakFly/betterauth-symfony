<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Feature;

use BetterAuth\Symfony\Core\Utils\Crypto;
use BetterAuth\Symfony\Feature\Port\AuthorizationTransactionStoreInterface;
use BetterAuth\Symfony\Feature\Port\OAuthClientPortInterface;

final readonly class OAuthService
{
    public function __construct(
        private OAuthClientPortInterface $clients,
        private AuthorizationTransactionStoreInterface $transactions,
        private int $transactionTtl = 600,
    ) {
    }

    public function beginAuthorization(string $provider, string $redirectUri): ?AuthorizationRequest
    {
        if (!$this->clients->allows($provider, $redirectUri)) {
            return null;
        }
        $state = Crypto::randomToken(32);
        $verifier = Crypto::randomToken(32);
        $this->transactions->store(new AuthorizationTransaction(
            'oauth', $provider, '', $redirectUri, $state, '', $verifier,
            new \DateTimeImmutable(sprintf('+%d seconds', $this->transactionTtl)),
        ));

        return new AuthorizationRequest(
            $this->clients->authorizationUrl($provider, $redirectUri, $state, $this->codeChallenge($verifier)),
            $state,
        );
    }

    public function completeAuthorization(string $provider, string $state, string $code): OAuthAuthenticationOutcome
    {
        $transaction = $this->transactions->consume('oauth', $state, new \DateTimeImmutable());
        if ($transaction === null || !hash_equals($transaction->provider, $provider)) {
            return OAuthAuthenticationOutcome::invalid();
        }

        return OAuthAuthenticationOutcome::completed($this->clients->exchangeAuthorizationCode(
            $provider, $code, $transaction->redirectUri, $transaction->codeVerifier,
        ));
    }

    private function codeChallenge(string $verifier): string
    {
        return Crypto::base64UrlEncode(hash('sha256', $verifier, true));
    }
}
