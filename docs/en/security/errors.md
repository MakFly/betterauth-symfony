# Authentication errors

## Objective

Keep client responses stable while retaining server-side diagnostic context.

## Activation and configuration

Use the bundle authenticator for protected firewalls and map API errors to a documented `application/problem+json` shape.

## Contract and flow

Expired values raise `TokenExpiredException`; malformed, wrong-type, or unverifiable values raise `InvalidTokenException`. The authenticator maps both to generic HTTP 401.

## Example

```json
{"type":"about:blank","title":"Unauthorized","status":401}
```

## Security and failures

Map invalid credentials, missing users, rate limits, and expired sessions consistently. Log class, request ID, and route only; never raw tokens or one-time values.

## Validation

Test statuses, content type, correlation IDs, and absence of token material in responses/logs.
