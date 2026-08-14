# Accès invité

## Objectif

Offrir un parcours invité limité sans le confondre avec une session utilisateur.

## Activation et configuration

Activer `features.guest`, un store dédié, purpose `guest` et TTL court.

## Contrat et flux

`issue()` stocke le hash ; `consume()` renvoie l’identifiant invité une seule fois ; le contrôleur limite les capacités.

## Exemple

```php
$token = $guestTokens->issue('guest:'.$cartId);
$guestId = $guestTokens->consume($token);
```

## Sécurité et erreurs

Séparer identifiants invités/utilisateurs, rate-limit, expiration et URL sans données ; échec générique.

## Validation

Tester usage unique, expiration, purpose et isolation du panier.
