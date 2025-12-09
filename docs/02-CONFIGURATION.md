# Configuration

Référence complète des options de configuration du bundle BetterAuth.

## Fichier de configuration

`config/packages/better_auth.yaml`

## Référence complète

```yaml
better_auth:
    mode: 'api'                       # api | session | hybrid
    secret: '%env(BETTER_AUTH_SECRET)%'

    session:
        lifetime: 604800              # 7 jours
        cookie_name: 'better_auth_session'

    token:
        lifetime: 3600                # 1h
        refresh_lifetime: 2592000     # 30j

    oauth:
        providers:
            google:
                enabled: true
                client_id: '%env(GOOGLE_CLIENT_ID)%'
                client_secret: '%env(GOOGLE_CLIENT_SECRET)%'
                redirect_uri: '%env(APP_URL)%/auth/oauth/google/callback'
            github:
                enabled: true
                client_id: '%env(GITHUB_CLIENT_ID)%'
                client_secret: '%env(GITHUB_CLIENT_SECRET)%'
                redirect_uri: '%env(APP_URL)%/auth/oauth/github/callback'
            microsoft:
                enabled: false
                client_id: '%env(MICROSOFT_CLIENT_ID)%'
                client_secret: '%env(MICROSOFT_CLIENT_SECRET)%'
                redirect_uri: '%env(APP_URL)%/auth/oauth/microsoft/callback'
            facebook:
                enabled: false
                client_id: '%env(FACEBOOK_CLIENT_ID)%'
                client_secret: '%env(FACEBOOK_CLIENT_SECRET)%'
                redirect_uri: '%env(APP_URL)%/auth/oauth/facebook/callback'
            discord:
                enabled: false
                client_id: '%env(DISCORD_CLIENT_ID)%'
                client_secret: '%env(DISCORD_CLIENT_SECRET)%'
                redirect_uri: '%env(APP_URL)%/auth/oauth/discord/callback'

    multi_tenant:
        enabled: false
        default_role: 'member'

    two_factor:
        enabled: true
        issuer: 'BetterAuth'
        backup_codes_count: 10

    magic_link:
        enabled: false
        lifetime: 900                 # 15 minutes

    email_verification:
        enabled: true
        lifetime: 86400               # 24h

    password_reset:
        enabled: true
        lifetime: 3600                # 1h

    guest_sessions:
        enabled: false
        lifetime: 86400               # 24h

    device_tracking:
        enabled: false

    security_monitoring:
        enabled: false

    controllers:
        enabled: true

    security:
        auto_configure: false         # restez à false si vous avez déjà votre security.yaml
        firewall_name: 'api'
        firewall_pattern: '^/api'
        public_routes_pattern: '^/auth'

    cors:
        auto_configure: true          # nécessite nelmio/cors-bundle

    routing:
        auto_configure: true
        custom_controllers_namespace: 'App\\Controller'

    openapi:
        path_prefix: ~                # auto-détection
        enabled: true
```

## Sections clés

- **mode** : `api` (stateless), `session` (cookies) ou `hybrid` (mix).
- **secret** : clé 32+ caractères, toujours via variable d’environnement (`BETTER_AUTH_SECRET`).
- **token/session** : durées d’accès/refresh, durée des sessions et nom du cookie.
- **oauth** : Google `[STABLE]`, GitHub/Facebook/Microsoft/Discord `[DRAFT]`; chaque provider a `enabled`, `client_id`, `client_secret`, `redirect_uri`.
- **two_factor / magic_link / email_verification / password_reset** : activer/désactiver et régler les durées.
- **guest_sessions / device_tracking / security_monitoring** : activer les fonctionnalités avancées.
- **controllers.enabled** : désactiver si vous fournissez vos propres contrôleurs.
- **security.auto_configure** : par défaut `false` pour ne pas écraser votre `security.yaml`; activez-le uniquement si vous laissez le bundle tout configurer.
- **cors.auto_configure** et **routing.auto_configure** : configuration automatique de CORS et du préfixe des routes personnalisées.
- **openapi** : documentation API Platform/OpenAPI sur les routes BetterAuth (`path_prefix` optionnel).

## Variables d’environnement

```env
# Obligatoires
BETTER_AUTH_SECRET=your-64-char-secret-here
APP_URL=https://myapp.com
APP_DOMAIN=myapp.com
APP_NAME=MyApp

# Providers OAuth
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
FACEBOOK_CLIENT_ID=
FACEBOOK_CLIENT_SECRET=
MICROSOFT_CLIENT_ID=
MICROSOFT_CLIENT_SECRET=
DISCORD_CLIENT_ID=
DISCORD_CLIENT_SECRET=

# Email / SMTP
MAILER_DSN=smtp://localhost:1025
MAILER_FROM_EMAIL=noreply@myapp.com
MAILER_FROM_NAME=MyApp
```
