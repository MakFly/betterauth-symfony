# Upgrade to 1.1

## Breaking: OAuth and OIDC are removed

Version 1.1 deliberately removes every OAuth and OpenID Connect service,
configuration key, port, outcome, and authorization transaction type from this
package. This is a SemVer-breaking change made before the v1.1.0 release.

Remove these settings and service references from your application:

- `better_auth.features.oauth` and `better_auth.features.oidc`
- `better_auth.feature_ports.oauth`, `oidc`, and `authorization_transactions`
- `better_auth.oidc_issuer`
- all BetterAuth OAuth/OIDC/authorization transaction service and port imports

Keep your existing PASETO, refresh-token, TOTP, magic-link, email-reset, guest,
device, monitoring, and multi-tenant integrations. If your application needs an
OAuth or OIDC client, own it directly outside BetterAuth and keep it separate
from the bundle's authentication contracts.
