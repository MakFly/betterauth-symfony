# Rotation du refresh

## Objectif

Rendre chaque refresh à usage unique et détecter les rejeux.

## Activation et configuration

Activer `refresh_token`, choisir TTL et brancher `RefreshTokenStoreInterface`.

## Contrat et flux

`issue()` renvoie une paire et persiste seulement le hash ; `rotate()` consomme atomiquement et renvoie `rotated`, `replayed` ou `invalid`.

## Exemple

```php
$pair = $refreshManager->issue($user->getUserIdentifier());
$outcome = $refreshManager->rotate($request->request->getString('refresh_token'));
```

## Sécurité et erreurs

Jeter l’ancien secret ; au rejeu révoquer la famille et exiger une reconnexion. Ne jamais journaliser la valeur brute.

## Validation

Tester succès, concurrence, expiration, révocation et mode désactivé.
