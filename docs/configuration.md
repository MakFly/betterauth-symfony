# Configuration Reference

Complete configuration reference for BetterAuth Symfony Bundle.

## Full Configuration

```yaml
better_auth:
    # Authentication mode
    mode: 'api'                    # 'session', 'api', or 'hybrid'

    # Secret key for token encryption
    secret: '%env(BETTER_AUTH_SECRET)%'

    # === ROUTING ===
    routing:
        prefix: '/auth'            # API prefix (e.g., '/api/v1/auth')
        name_prefix: 'better_auth_' # Route name prefix

    # === CONTROLLERS ===
    controllers:
        enabled: true              # Master switch

        groups:
            credentials:
                enabled: true
                class: null        # Override: App\Controller\CustomController

            token:
                enabled: true
                class: null

            session:
                enabled: true
                class: null

            oauth:
                enabled: true
                class: null

            two_factor:
                enabled: true
                class: null

            password_reset:
                enabled: true
                class: null

            email_verification:
                enabled: true
                class: null

            magic_link:
                enabled: true
                class: null

            guest_session:
                enabled: true
                class: null

        endpoints:
            register:
                enabled: true
                path: null         # Override path (e.g., '/signup')
            # ... other endpoints

    # === SECURITY ===
    security:
        auto_configure: true       # Auto-configure security.yaml
        firewall_name: 'api'       # Protected firewall name
        firewall_pattern: '^/api'  # Protected routes pattern
        public_routes_pattern: null # Auto-derived from routing.prefix

    # === SESSION ===
    session:
        lifetime: 604800           # 7 days in seconds
        cookie_name: 'better_auth_session'

    # === TOKEN ===
    token:
        lifetime: 3600             # Access token: 1 hour
        refresh_lifetime: 2592000  # Refresh token: 30 days

    # === OAUTH ===
    oauth:
        providers:
            google:
                enabled: false
                client_id: '%env(GOOGLE_CLIENT_ID)%'
                client_secret: '%env(GOOGLE_CLIENT_SECRET)%'
                redirect_uri: '%env(GOOGLE_REDIRECT_URI)%'

            github:
                enabled: false
                client_id: '%env(GITHUB_CLIENT_ID)%'
                client_secret: '%env(GITHUB_CLIENT_SECRET)%'
                redirect_uri: '%env(GITHUB_REDIRECT_URI)%'

            # Available: google, github, facebook, discord, microsoft, twitter, apple

    # === MULTI-TENANT ===
    multi_tenant:
        enabled: true
        default_role: 'member'

    # === TWO-FACTOR ===
    two_factor:
        enabled: true
        issuer: 'MyApp'            # Shown in authenticator apps
        backup_codes_count: 10
```

## Environment Variables

Required environment variables:

```bash
# .env
BETTER_AUTH_SECRET=your-secret-key-at-least-32-chars
FRONTEND_URL=http://localhost:5173

# Optional: OAuth providers
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/oauth/google/callback
```

## Minimal Configuration

For a basic API setup:

```yaml
better_auth:
    mode: 'api'
    secret: '%env(BETTER_AUTH_SECRET)%'

    routing:
        prefix: '/api/v1/auth'
```

## Controller Groups

| Group | Endpoints |
|-------|-----------|
| `credentials` | register, login, login/2fa |
| `token` | me, refresh, logout, revoke-all |
| `session` | sessions (list), sessions/{id} (revoke) |
| `oauth` | oauth/providers, oauth/{provider}, oauth/{provider}/callback |
| `two_factor` | 2fa/setup, 2fa/validate, 2fa/verify, 2fa/disable, 2fa/status |
| `password_reset` | password/forgot, password/reset, password/verify-token |
| `email_verification` | email/send-verification, email/verify, email/verification-status |
| `magic_link` | magic-link/send, magic-link/verify |
| `guest_session` | guest/create, guest/{token}, guest/convert |

## Endpoint Names

Use these names in `controllers.endpoints`:

- `register`, `login`, `login_2fa`
- `me`, `refresh`, `logout`, `revoke_all`
- `sessions_list`, `sessions_revoke`
- `oauth_providers`, `oauth_redirect`, `oauth_url`, `oauth_callback`
- `2fa_setup`, `2fa_validate`, `2fa_verify`, `2fa_disable`, `2fa_status`, `2fa_reset`, `2fa_backup_codes`
- `password_forgot`, `password_reset`, `password_verify_token`
- `email_send`, `email_verify`, `email_status`
- `magic_link_send`, `magic_link_verify`, `magic_link_verify_get`
- `guest_create`, `guest_get`, `guest_convert`, `guest_delete`
