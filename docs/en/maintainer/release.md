# Release

## Objective

Publish a reproducible package artifact with tested contracts and complete mirrored docs.

## Activation and configuration

Update version/changelog according to project policy, verify Composer metadata, and ensure CI uses the supported PHP/Symfony matrix. Do not publish from a dirty or unreviewed tree.

## Contract and flow

Review diff → run unit/static/documentation gates → build package artifact → dispatch the `Release` workflow from the reviewed `main` commit with a stable `vMAJOR.MINOR.PATCH` version. The workflow publishes nothing: it reruns the Symfony matrix and records the exact validated SHA. After it succeeds, disable the Packagist push webhook, use the protected administrator process to create the GitHub release and tag together on that SHA, then restore release immutability. Finally perform a controlled Packagist synchronization and leave the push webhook disabled again. Release notes must call out breaking config or port changes.

## Example

```bash
git diff --check
composer validate --strict
composer test
composer phpstan
vendor/bin/phpunit tests/Documentation
```

## Security and failures

Never include secrets or raw token material in artifacts. Stop on failing gates, dependency drift, missing mirrored pages, or unreviewed public API changes.

## Validation

Install the produced artifact in a disposable Symfony app, exercise bearer authentication and enabled features, then compare the tag to the reviewed commit.
