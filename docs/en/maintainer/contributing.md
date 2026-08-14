# Contributing

## Objective

Make focused, reviewable changes that preserve the package’s application-owned boundaries.

## Activation and configuration

Read `README.md`, `UPGRADE-1.1.md`, and [architecture](../getting-started/architecture.md) before changing behavior. Use Composer and PHP tooling; do not introduce generated routes/entities.

## Contract and flow

A contribution updates the smallest source surface, its public contract tests, and mirrored docs where behavior is user-facing. Ports remain interfaces; applications implement storage and delivery.

## Example

```bash
git diff --check
composer test
composer phpstan
```

## Security and failures

Never commit secrets, raw token fixtures, or unrelated formatting churn. Document compatibility and migration impact; ask before changing a public contract.

## Validation

Provide reproduction, tests, docs links, and known limitations in the pull request. Review the final diff for scope and redaction.
