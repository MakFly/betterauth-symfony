# Liens magiques

## Objectif

Fournir une preuve email courte et à usage unique.

## Activation et configuration

Activer `features.magic_link`, brancher le store et le mailer applicatif ; le bundle ne crée pas de route.

## Contrat et flux

Le store reçoit hash SHA-256 et expiration ; callback HTTPS consomme atomiquement purpose/hash/état inutilisé.

## Exemple

```php
$value = $magicLinks->issue($id);
$mailer->send($email, $value);
```

## Sécurité et erreurs

URL opaque sans données de compte, TTL court, réponses génériques, rate limit et HTTPS ; une valeur consommée/expirée est toujours refusée.

## Validation

Tester inconnus, expiration, mauvais purpose, rejeu et absence de valeur brute en base.
