# Configuration reference

```yaml
better_auth:
    secret: '%env(BETTER_AUTH_SECRET)%' # required; at least 32 bytes
    user_id_claim: sub                  # access-token subject claim
    access_token:
        ttl: 3600                       # seconds
        parser:
            max_token_length: 8192
            max_json_length: 4096
            max_claim_count: 32
            max_claim_depth: 4
    refresh_token:
        enabled: true
        ttl: 2592000                    # seconds
        store: App\Security\RefreshTokenStore # required when enabled
    token_extractors:
        authorization_header: { enabled: true, max_length: 8192 }
        cookie: { enabled: false, name: access_token, max_length: 8192 }
    features:
        oauth: false
        oidc: false
        totp: false
        magic_link: false
        email_reset: false
        guest: false
        device: false
        monitoring: false
        multi_tenant: false
    feature_ports:
        oauth: App\Security\OAuthClientPort
        oidc: App\Security\OidcClientPort
        authorization_transactions: App\Security\AuthorizationTransactionStore
        totp: App\Security\TotpStore
        magic_link: App\Security\OneTimeTokenStore
        email_reset: App\Security\OneTimeTokenStore
        guest: App\Security\OneTimeTokenStore
        device: App\Security\DeviceStore
        monitoring: App\Security\SecurityMonitoringStore
        multi_tenant: App\Security\TenantMembershipStore
    oidc_issuer: 'https://id.example.test' # external issuer; required only when OIDC is enabled
```

`refresh_token.store` implements `BetterAuth\Symfony\Token\RefreshTokenStoreInterface`.
Its `store`, `find`, `revoke`, `revokeForUser`, and `rotate` methods receive
hashes only. `rotate` must conditionally consume an unspent, unexpired record
and persist exactly one replacement in the same transaction. It returns a typed
`rotated`, `replayed`, or `invalid` outcome. No persistence implementation is
bundled.

For `security.firewalls.<name>.better_auth: ~`, Symfony passes the firewall's
configured user provider to BetterAuth. The identifier read from `user_id_claim`
is passed to `UserProviderInterface::loadUserByIdentifier()`.

When a feature flag is enabled, its matching `feature_ports` entry is required.
The bundle registers the corresponding service only then; it never registers a
route, controller, entity, mapping, or migration. Parser limits are applied to
the PASETO parser and extractors reject oversized values before parsing.

OAuth and OIDC are relying-party/client services, not provider endpoints. Both
require `feature_ports.authorization_transactions`, whose `consume()` method
must atomically consume one unexpired state exactly once. The bundle creates
state and PKCE verifier transactions; OIDC also creates a nonce. The OAuth/OIDC
client ports must exchange the code with that verifier. The OIDC port must
validate the external ID token's signature, issuer, audience, and nonce before
returning a typed valid identity result. BetterAuth PASETO access tokens are
never OIDC ID tokens.

Before any OAuth state is stored, `OAuthClientPortInterface::allows()` must
approve the provider and redirect URI. `authorizationUrl()` must send the PKCE
challenge as `code_challenge_method=S256`; plaintext PKCE is unsupported.

`TotpStoreInterface` receives authenticated ciphertext only. `TotpService`
derives a domain-separated encryption key from `better_auth.secret`, encrypts
the seed before `save()`, and decrypts it only while verifying a code.

`OneTimeTokenStoreInterface::consume()` must atomically match purpose and hash
while unexpired and unconsumed, marking the row consumed in that same operation.
The raw one-time value never reaches the store.
