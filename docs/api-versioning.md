# API Versioning Guide

This guide explains how to configure API versioning in BetterAuth.

## Configuration

Set the API prefix in `better_auth.yaml`:

```yaml
better_auth:
    routing:
        prefix: '/api/v1/auth'    # Routes: /api/v1/auth/login, /api/v1/auth/register, etc.
        name_prefix: 'api_v1_'    # Route names: api_v1_login, api_v1_register, etc.
```

## Default Routes

With default configuration (`prefix: '/auth'`):

| Endpoint | Path |
|----------|------|
| Register | `POST /auth/register` |
| Login | `POST /auth/login` |
| Me | `GET /auth/me` |
| Refresh | `POST /auth/refresh` |
| Logout | `POST /auth/logout` |

## Versioned Routes

With `prefix: '/api/v1/auth'`:

| Endpoint | Path |
|----------|------|
| Register | `POST /api/v1/auth/register` |
| Login | `POST /api/v1/auth/login` |
| Me | `GET /api/v1/auth/me` |
| Refresh | `POST /api/v1/auth/refresh` |
| Logout | `POST /api/v1/auth/logout` |

## Custom Endpoint Paths

Override individual endpoint paths:

```yaml
better_auth:
    routing:
        prefix: '/api/v1/auth'

    controllers:
        endpoints:
            register:
                path: '/signup'      # /api/v1/auth/signup
            login:
                path: '/signin'      # /api/v1/auth/signin
```

## Multiple API Versions

To support multiple API versions, use custom controllers:

```yaml
# config/packages/better_auth.yaml
better_auth:
    routing:
        prefix: '/api/v2/auth'    # New version

    controllers:
        groups:
            credentials:
                class: App\Controller\Api\V2\AuthController
```

For legacy support, define additional routes manually:

```yaml
# config/routes.yaml
# V2 routes (from bundle)
better_auth:
    resource: .
    type: better_auth

# V1 legacy routes (custom)
api_v1_auth:
    resource: '../src/Controller/Api/V1/'
    type: attribute
    prefix: '/api/v1/auth'
```

## Security Configuration

When changing the prefix, update security.yaml accordingly:

```yaml
better_auth:
    routing:
        prefix: '/api/v1/auth'

    security:
        auto_configure: true
        firewall_pattern: '^/api'
        # public_routes_pattern is auto-derived from routing.prefix
```

The bundle automatically configures:
- Public firewall for auth routes (`^/api/v1/auth`)
- Protected firewall for API routes (`^/api`)
