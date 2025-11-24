better_auth:
    mode: '{{MODE}}'  # 'api' (stateless tokens), 'session' (stateful cookies), or 'hybrid'
    secret: '%env(BETTER_AUTH_SECRET)%'

    session:
        lifetime: 604800  # 7 days (used in session mode only)
        cookie_name: 'better_auth_session'

    token:
        lifetime: 7200           # Access token: 2 hours
        refresh_lifetime: 2592000  # Refresh token: 30 days

    oauth:
        providers:
{{OAUTH_PROVIDERS}}

    multi_tenant:
        enabled: false  # Disabled by default
        default_role: 'member'
