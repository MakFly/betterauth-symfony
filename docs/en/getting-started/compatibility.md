# Compatibility

## Objective

Check platform constraints before integrating the package.

## Activation and configuration

Supported baseline: PHP `^8.4`, OpenSSL, Symfony `^6.4|^7.0|^8.0`, and `paragonie/paseto` 3.1+. Install locked dependencies with Composer.

## Contract and flow

Tokens use encrypted PASETO v4.local. PSR cache, log, and event interfaces are dependencies; persistence and mail delivery remain application concerns.

## Example

```bash
php -v
php -m | grep -E 'openssl|json'
composer check-platform-reqs
```

## Security and failures

Do not bypass a missing OpenSSL extension or substitute an untested token implementation. Review dependency updates before deployment.

## Validation

Run `composer check-platform-reqs` and the package test matrix on every supported PHP/Symfony combination.
