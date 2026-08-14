# Publication

## Objectif

Publier un artefact reproductible après tous les contrôles.

## Activation et configuration

Mettre à jour version/changelog selon la politique, vérifier métadonnées Composer et matrice CI ; ne pas publier un arbre sale.

## Contrat et flux

Revue → contrôles → artefact → désactivation du webhook push Packagist → création du tag stable `vMAJOR.MINOR.PATCH` revu via le processus administrateur protégé du dépôt → réactivation de l’immutabilité des releases → déclenchement du workflow `Release` depuis le même commit `main`. Le workflow refuse un tag absent ou divergent, rejoue la matrice Symfony, crée un brouillon et publie la release immuable. Effectuer ensuite une synchronisation Packagist contrôlée uniquement après le succès du workflow, puis laisser le webhook push désactivé ; signaler les changements de configuration/ports.

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
