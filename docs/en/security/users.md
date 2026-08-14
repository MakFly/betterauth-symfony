# Users

## Objective

Integrate application-owned users with Symfony Security without coupling the bundle to an entity or ORM.

## Activation and configuration

Configure the firewall/provider and implement `loadUserByIdentifier()` for `better_auth.user_id_claim` (default `sub`). Optionally implement `AuthUserInterface` and `PasswordHolderInterface`.

## Contract and flow

After verification, `BetterAuthAuthenticator` passes the identifier to the provider and returns a self-validating passport. Current roles come from that provider.

## Example

```php
final class User implements UserInterface, AuthUserInterface, PasswordHolderInterface
{
    public function getUserIdentifier(): string { return $this->id; }
}
```

## Security and failures

Use stable non-sensitive identifiers. Never put passwords or secrets in claims. Missing users should be unauthorized without account enumeration.

## Validation

Test provider lookup, deleted users, roles, `eraseCredentials()`, and absent/non-string identifiers.
