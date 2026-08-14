# Security monitoring

## Objective

Create a durable, queryable trail for authentication risk decisions without turning logs into a credential store.

## Activation and configuration

Enable `features.monitoring` and map `feature_ports.monitoring` to `SecurityMonitoringStoreInterface`. Choose retention, alert routing, and authorized readers in the application.

## Contract and flow

`SecurityMonitoringService::record($userId, $type, $severity, $details)` delegates an event with `type`, `severity`, `details`, and an ISO-8601 `occurred_at` timestamp. Record events at the application boundary after the decision.

## Example

```php
$monitoring->record($id, 'refresh_replay', 'high', ['route' => 'auth_refresh']);
```

## Security and failures

Redact tokens, passwords, one-time values, and unnecessary personal data. Validate severity and event types in the store, bound detail size, and fail closed for alerting without breaking authentication availability.

## Validation

Assert replay, revocation, sign-in failure, TOTP change, and device-change events are emitted with no secret values. Test retention and reader authorization.
