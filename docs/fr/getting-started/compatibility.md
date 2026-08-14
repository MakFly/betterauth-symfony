# Compatibilité

## Objectif

Vérifier les contraintes de plateforme avant intégration.

## Activation et configuration

PHP `^8.4`, OpenSSL, Symfony `^6.4|^7.0|^8.0` et `paragonie/paseto` 3.1+. Installer les dépendances verrouillées avec Composer.

## Contrat et flux

Les jetons sont PASETO v4.local ; cache, logs, événements, persistance et courriel restent des responsabilités distinctes.

## Exemple

```bash
php -v
php -m | grep -E 'openssl|json'
composer check-platform-reqs
```

## Sécurité et erreurs

Ne pas contourner OpenSSL ni remplacer l’implémentation cryptographique sans validation.

## Validation

Lancer `composer check-platform-reqs` et la matrice de tests PHP/Symfony supportée.
