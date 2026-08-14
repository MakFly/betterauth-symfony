# Upgrade to 1.0

1. Remove the `betterauth/multimodal-php` dependency and every Composer path
   repository pointing to it. Version 1 conflicts with that legacy package.
2. Remove BetterAuth-generated entities, Doctrine mappings and migrations. Keep
   the application's own user and persistence model.
3. Remove generated BetterAuth routes/controllers and installer commands. Create
   login, refresh, logout, OAuth and recovery routes in the application.
4. Replace `mode: session|api|hybrid` with PASETO access tokens. Sessions are not
   a bundle authentication mode in 1.0.
5. Implement `RefreshTokenStoreInterface` using token hashes and an atomic,
   conditional `rotate()` operation. Handle its `rotated`, `replayed`, and
   `invalid` outcomes, then configure `better_auth.refresh_token.store`.
6. Replace custom-authenticator wiring with `better_auth: ~` under the existing
   firewall and retain the application's configured provider.
7. Explicitly register any optional capability and its routes. All feature flags
   default to `false` and no integration is auto-configured. OAuth/OIDC require
   an atomic `AuthorizationTransactionStoreInterface` for state/PKCE (and OIDC
   nonce) lifecycle. OIDC is now a relying-party/client integration: validate
   the external ID token through the application port; do not treat a BetterAuth
   PASETO access token as an ID token.
8. Update TOTP persistence to store only the ciphertext provided by
   `TotpStoreInterface`; raw seeds are no longer valid store values. Make each
   one-time-token consume operation an atomic purpose+hash+unexpired+unconsumed
   compare-and-set.
9. For OAuth, implement `OAuthClientPortInterface::allows()` to approve every
   provider/redirect pair before state is stored, and build authorization URLs
   with S256 PKCE only.
