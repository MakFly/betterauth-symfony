# Tests

## Objectif

Exécuter les contrôles de code, wiring et documentation.

## Activation et configuration

Depuis la racine, installer avec Composer ; PHPUnit utilise `phpunit.xml.dist`, PHPStan `phpstan.neon`.

## Contrat et flux

Les tests couvrent contrats, intégration Symfony, parité des arbres français/anglais et liens locaux.

## Exemple

```bash
composer install
composer test
composer phpstan
vendor/bin/phpunit tests/Documentation
```

## Sécurité et erreurs

Secrets uniquement dans fixtures déterministes ; ne jamais imprimer de jeton. Un échec de parité/lien bloque la release.

## Validation

Conserver codes de sortie et sortie des commandes dans le compte-rendu.
