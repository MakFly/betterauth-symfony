# Contribuer

## Objectif

Garder des changements ciblés et compatibles avec les frontières applicatives.

## Activation et configuration

Lire README, UPGRADE-1.1 et l’[architecture](../getting-started/architecture.md). Utiliser Composer/PHP ; ne pas ajouter routes ou entités générées.

## Contrat et flux

Modifier la plus petite surface, les tests du contrat et les deux langues si le comportement est public.

## Exemple

```bash
git diff --check
composer test
composer phpstan
```

## Sécurité et erreurs

Aucun secret, fixture de token brut ou bruit sans rapport ; documenter compatibilité et migration.

## Validation

Fournir reproduction, tests, liens docs et limites dans la proposition.
