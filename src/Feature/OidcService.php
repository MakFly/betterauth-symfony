<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Feature;

use BetterAuth\Symfony\Core\Utils\Crypto;
use BetterAuth\Symfony\Feature\Port\AuthorizationTransactionStoreInterface;
use BetterAuth\Symfony\Feature\Port\OidcClientPortInterface;
use BetterAuth\Symfony\Feature\Port\OidcIdentityValidationStatus;

/** Safe OpenID Connect relying-party orchestration; this is not an OIDC provider. */
final readonly class OidcService
{
    public function __construct(
        private OidcClientPortInterface $clients,
        private AuthorizationTransactionStoreInterface $transactions,
        private string $issuer,
        private int $transactionTtl = 600,
    ) {
    }

    public function beginAuthorization(string $clientIdentifier, string $redirectUri): ?AuthorizationRequest
    {
        if (!$this->clients->allows($clientIdentifier, $redirectUri)) {
            return null;
        }
        $state = Crypto::randomToken(32);
        $nonce = Crypto::randomToken(32);
        $verifier = Crypto::randomToken(32);
        $this->transactions->store(new AuthorizationTransaction(
            'oidc', $this->issuer, $clientIdentifier, $redirectUri, $state, $nonce, $verifier,
            new \DateTimeImmutable(sprintf('+%d seconds', $this->transactionTtl)),
        ));

        return new AuthorizationRequest(
            $this->clients->authorizationUrl($this->issuer, $clientIdentifier, $redirectUri, $state, $nonce, $this->codeChallenge($verifier)),
            $state,
        );
    }

    public function completeAuthorization(string $clientIdentifier, string $state, string $code): OidcAuthenticationOutcome
    {
        $transaction = $this->transactions->consume('oidc', $state, new \DateTimeImmutable());
        if ($transaction === null || !hash_equals($transaction->clientIdentifier, $clientIdentifier) || !hash_equals($transaction->provider, $this->issuer)) {
            return OidcAuthenticationOutcome::invalid();
        }
        $result = $this->clients->exchangeAndValidateAuthorizationCode(
            $this->issuer, $clientIdentifier, $code, $transaction->redirectUri, $transaction->codeVerifier, $transaction->nonce,
        );
        if ($result->status !== OidcIdentityValidationStatus::Valid || $result->subject === null || $result->claims === null) {
            return OidcAuthenticationOutcome::invalid();
        }

        return OidcAuthenticationOutcome::authenticated($result->subject, $result->claims);
    }

    private function codeChallenge(string $verifier): string
    {
        return Crypto::base64UrlEncode(hash('sha256', $verifier, true));
    }
}
