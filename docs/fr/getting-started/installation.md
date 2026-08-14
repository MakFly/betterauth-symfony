# Installation

## Objectif

Installer le bundle sans transférer à la bibliothèque les utilisateurs, routes, entités ou migrations.

## Activation et configuration

`composer require betterauth/symfony-bundle`, puis `php bin/console cache:clear`. Enregistrer `BetterAuth\Symfony\BetterAuthBundle` si Flex ne l’a pas fait ; définir `BETTER_AUTH_SECRET` (32 octets minimum) et le firewall/provider.

## Contrat et flux

Le bundle enregistre ses services et ports. Les contrôleurs émettent les jetons et les stores applicatifs persistent hashes/ciphertexts.

## Exemple

```yaml
better_auth:
    secret: '%env(BETTER_AUTH_SECRET)%'
```

## Sécurité et erreurs

Utiliser un gestionnaire de secrets, HTTPS et des journaux masqués ; un secret absent ou trop court doit faire échouer le démarrage.

## Validation

Exécuter `php bin/console lint:yaml config`, vider le cache, appeler une route protégée et vérifier le 401 sans jeton.
