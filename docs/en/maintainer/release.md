# Release

## Objective

Publish a reproducible package artifact with tested contracts and complete mirrored docs.

## Activation and configuration

Update version/changelog according to project policy, verify Composer metadata, and ensure CI uses the supported PHP/Symfony matrix. Do not publish from a dirty or unreviewed tree.

## Contract and flow

Review diff → run unit/static/documentation gates → build package artifact → disable the Packagist push webhook → create the reviewed stable `vMAJOR.MINOR.PATCH` tag through the repository’s protected administrator process → restore release immutability → dispatch the `Release` workflow from the same `main` commit. The workflow rejects a missing or mismatched tag, reruns the Symfony matrix, creates a draft, and publishes the immutable release. Only after that workflow succeeds, perform a controlled Packagist synchronization and leave the push webhook disabled again. Release notes must call out breaking config or port changes.

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
