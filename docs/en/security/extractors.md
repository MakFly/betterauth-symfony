# Token extractors

## Objective

Accept an access token only from an explicitly configured request boundary.

## Activation and configuration

```yaml
better_auth:
    token_extractors:
        authorization_header: { enabled: true, max_length: 8192 }
        cookie: { enabled: false, name: access_token, max_length: 8192 }
```

## Contract and flow

The header extractor reads exactly `Authorization: Bearer <token>`; the cookie extractor reads the configured cookie. A chain returns the first non-null value, then the authenticator verifies it.

## Example

```http
GET /api/me HTTP/1.1
Authorization: Bearer v4.local....
```

## Security and failures

Reject missing, malformed, whitespace-containing, and oversized values. Cookie flows require HTTPS, `Secure`, suitable `HttpOnly`/`SameSite`, and CSRF protection.

## Validation

Test precedence, disabled extractors, max length, malformed schemes, and no-token requests.
