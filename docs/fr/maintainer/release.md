# Publication

## Objectif

Publier un artefact reproductible après tous les contrôles.

## Activation et configuration

Mettre à jour version/changelog selon la politique, vérifier métadonnées Composer et matrice CI ; ne pas publier un arbre sale.

## Contrat et flux

Revue → contrôles → artefact → déclenchement du workflow `Release` depuis le commit `main` revu avec une version stable `vMAJOR.MINOR.PATCH`. Le workflow ne publie rien : il rejoue la matrice Symfony et consigne le SHA exact validé. Après son succès, désactiver le webhook push Packagist, utiliser le processus administrateur protégé pour créer ensemble la release GitHub et son tag sur ce SHA, puis réactiver l’immutabilité des releases. Effectuer enfin une synchronisation Packagist contrôlée et laisser le webhook push désactivé ; signaler les changements de configuration/ports.

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
