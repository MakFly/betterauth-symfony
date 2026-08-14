# BetterAuth Symfony

## Objective

Symfony authentication primitives for application-owned users and persistence.

## Activation and configuration

Start with [installation](getting-started/installation.md) and [security configuration](config/security.md); provide a secret and firewall/provider.

## Contract and flow

The bundle verifies encrypted PASETO access tokens and exposes optional services/ports. It creates no application routes or entities.

## Example

Read [architecture](getting-started/architecture.md), then choose the relevant authentication feature.

## Security and failures

Keep secrets, raw tokens, and one-time values out of logs and persistence. See [errors](security/errors.md) and [troubleshooting](config/troubleshooting.md).

## Validation

Run the package tests and documentation parity/link checks before release.
