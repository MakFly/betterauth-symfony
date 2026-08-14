# Jetons d’accès PASETO

## Objectif

Émettre et vérifier des jetons chiffrés avec parsing borné et issuer stable.

## Activation et configuration

Configurer `better_auth.secret` à 32 octets minimum et les limites `access_token.parser`.

## Contrat et flux

`createAccessToken()` ajoute sujet, identifiant, `type: access`, issuer `betterauth`, émission et expiration ; `parseAccessToken()` vérifie l’ensemble.

## Exemple

```php
$token = $tokens->createAccessToken($user->getUserIdentifier(), [], 3600);
$claims = $tokens->parseAccessToken($token);
```

## Sécurité et erreurs

`decode()` et `isExpired()` n’autorisent jamais. Les valeurs invalides, expirées ou d’un mauvais type/issuer deviennent 401 générique.

## Validation

Tester altération, expiration, claims absents, longueur et profondeur.
