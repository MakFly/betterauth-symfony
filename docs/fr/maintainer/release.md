# Publication

## Objectif

Publier un artefact reproductible après tous les contrôles.

## Activation et configuration

Mettre à jour version/changelog selon la politique, vérifier métadonnées Composer et matrice CI ; ne pas publier un arbre sale.

## Contrat et flux

Revue → contrôles → artefact → tag/release protégé ; signaler les changements de configuration/ports.

## Exemple

```bash
git diff --check
composer validate --strict
composer test
composer phpstan
vendor/bin/phpunit tests/Documentation
```

## Sécurité et erreurs

Stopper sur échec, dérive de dépendance, page manquante ou API publique non revue ; aucun secret dans l’artefact.

## Validation

Installer l’artefact dans une app Symfony jetable et tester bearer et fonctionnalités activées.
