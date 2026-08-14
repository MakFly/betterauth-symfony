# Persistance Doctrine atomique

## Objectif

Garantir une rotation à usage unique sous concurrence ; le bundle ne crée ni entités ni migrations.

## Activation et configuration

Implémenter `RefreshTokenStoreInterface` et référencer le service dans `refresh_token.store`.

## Contrat et flux

Dans une transaction, modifier la ligne non révoquée/non expirée, la marquer consommée et insérer exactement un remplacement ; retourner `Rotated`, `Replayed` ou `Invalid`.

## Exemple

```text
UPDATE ... WHERE hash = current AND revoked = false AND expires_at > now
Puis INSERT replacement dans la même transaction.
```

## Sécurité et erreurs

Hash unique et aucun refresh brut. Une transaction partielle déconnecte ou permet la double utilisation.

## Validation

Deux rotations concurrentes : exactement un succès, un rejeu/invalide, une seule ligne de remplacement.
