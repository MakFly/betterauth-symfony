# Devices

## Objective

Record a recognizable login device so the application can show sessions and ask for step-up verification. This is an audit/risk signal, not authentication.

## Activation and configuration

Enable `features.device` and provide `feature_ports.device` with a service implementing `DeviceStoreInterface`:

```yaml
better_auth:
    features: { device: true }
    feature_ports: { device: App\Security\DeviceStore }
```

## Contract and flow

Call `DeviceService::record($userId, $userAgent, $ipAddress, $attributes)` after successful authentication. Attributes are sorted, then the service stores a SHA-256 fingerprint plus metadata and returns that fingerprint. The store owns persistence and listing/revocation semantics.

## Example

```php
$fingerprint = $devices->record($user->getUserIdentifier(), $request->headers->get('User-Agent', ''), $request->getClientIp() ?? 'unknown', ['platform' => 'web']);
```

## Security and failures

Do not use a fingerprint as a password or sole factor. Minimize IP/user-agent retention, protect the review endpoint, and handle missing client IPs. Hashing does not make a raw IP harmless.

## Validation

Test stable attributes produce the same fingerprint, changed attributes produce a new one, and the store receives no raw credential or token. Exercise device review and revocation through the application route.
