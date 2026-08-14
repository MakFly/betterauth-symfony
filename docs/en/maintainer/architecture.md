# Maintainer architecture

## Objective

Keep the bundle small, framework-native, and safe to embed in applications with different persistence models.

## Activation and configuration

Core wiring lives under `src/DependencyInjection`; authentication under `src/Security`, token primitives under `src/Core` and `src/Token`, and optional behavior under `src/Feature`.

## Contract and flow

Public interfaces define stores and signing boundaries. The extension turns configuration into services; the application owns controllers, routes, users, entities, migrations, mail, and policy.

## Example

```text
Configuration → Extension → Token/Authenticator services → application ports
```

## Security and failures

Preserve one-use and hash-only contracts, bounded parsing, generic errors, and no accidental authorization from inspection helpers. Avoid adding stateful persistence to the bundle.

## Validation

Run the complete test/static/docs gates and inspect the compiled service graph when changing wiring or public interfaces.
