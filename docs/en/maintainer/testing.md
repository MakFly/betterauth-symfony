# Testing

## Objective

Run the same gates used to review a package change, including documentation integrity.

## Activation and configuration

From `betterauth-symfony/`, install dependencies with Composer. The package test suite uses `phpunit.xml.dist`; static analysis uses `phpstan.neon`.

## Contract and flow

Unit tests cover token/feature contracts; integration tests cover Symfony wiring. Documentation tests compare English/French paths/headings and resolve local links.

## Example

```bash
composer install
composer test
composer phpstan
vendor/bin/phpunit tests/Documentation
```

## Security and failures

Use deterministic test secrets only in fixtures. Do not print tokens. Treat a parity/link failure as a release blocker.

## Validation

Run the commands from a clean dependency state and retain exit codes/output in the review report.
