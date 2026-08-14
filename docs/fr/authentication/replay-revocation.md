# Rejeu et révocation

## Objectif

Fournir une révocation ciblée et un signal de compromission fiable.

## Activation et configuration

Brancher `RefreshTokenStoreInterface` avec `revoke`, `revokeForUser` et rotation atomique.

## Contrat et flux

Un hash disponible tourne ; un hash consommé est `replayed` ; absence/expiration/révocation est `invalid`.

## Exemple

```php
$refresh->revoke($raw);
$refresh->revokeAll($user->getUserIdentifier());
```

## Sécurité et erreurs

Persister seulement hashes et métadonnées ; traiter le rejeu comme compromission et ne jamais accepter un identifiant utilisateur fourni par le client.

## Validation

Tester logout ciblé, logout global, rejeu, expiration et concurrence.
