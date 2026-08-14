# Architecture mainteneur

## Objectif

Garder un bundle petit, natif Symfony et adaptable aux persistances applicatives.

## Activation et configuration

Wiring dans `src/DependencyInjection`, auth dans `src/Security`, tokens dans `src/Core`/`src/Token`, features dans `src/Feature`.

## Contrat et flux

Les interfaces définissent stores/signature ; l’application possède contrôleurs, routes, utilisateurs, entités, migrations, mails et politiques.

## Exemple

```text
Configuration → Extension → Services token/authenticator → ports applicatifs
```

## Sécurité et erreurs

Préserver usage unique, hash-only, parsing borné, erreurs génériques et absence d’autorisation via helpers d’inspection.

## Validation

Lancer tests, PHPStan et docs ; inspecter le graphe compilé lors d’un changement de wiring.
