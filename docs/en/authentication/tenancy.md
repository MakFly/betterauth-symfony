# Tenancy

## Objective

Centralize membership checks while keeping tenant isolation in application data access.

## Activation and configuration

Enable `features.multi_tenant` and map `feature_ports.multi_tenant` to `TenantMembershipStoreInterface`. Define how the trusted host, route, or session selects a tenant.

## Contract and flow

`TenantMembershipService::allows($userId, $tenantId)` delegates to `hasMembership()`. Resolve and validate tenant context before loading data or issuing a tenant-scoped access token.

## Example

```php
$tenant = $tenantResolver->fromRequest($request);
if (!$tenants->allows($user->getUserIdentifier(), $tenant->id)) {
    throw $accessDenied;
}
```

## Security and failures

Never trust only a client tenant claim. Apply the check to every repository query and mutation, avoid cross-tenant cache keys, and return a non-enumerating denial.

## Validation

Test missing, unknown, member, and non-member contexts across reads and writes; include a token carrying a different tenant and assert it cannot cross boundaries.
