# Claims

## Objectif

Définir un contrat de claims réduit et interopérable.

## Activation et configuration

L’identifiant par défaut est `sub` ; changer `user_id_claim` exige l’accord de tous les producteurs/consommateurs. Les limites sont sous `access_token.parser`.

## Contrat et flux

Les jetons portent `sub`, `type`, `iat`, `exp`, `iss` et peuvent recevoir des claims structurés.

## Exemple

```php
$tokens->createAccessToken($id, ['tenant' => 'acme', 'scope' => ['read']], 900);
```

## Sécurité et erreurs

Claims courts, stables et non sensibles ; rôles et appartenance courants restent dans l’état applicatif.

## Validation

Tester aller-retour, limites et absence de mots de passe/secrets.
