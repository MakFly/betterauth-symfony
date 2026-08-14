# TOTP

## Objectif

Ajouter un second facteur temporel avec graine chiffrée au repos.

## Activation et configuration

Activer `features.totp`, brancher `TotpStoreInterface` et conserver un secret BetterAuth de 32 octets minimum.

## Contrat et flux

`enroll()` renvoie secret/URI et stocke le ciphertext ; `verify()` accepte six chiffres, SHA-256, 30 secondes et fenêtre 0–2.

## Exemple

```php
$setup = $totp->enroll($id, $email);
$valid = $totp->verify($id, $code, 1);
```

## Sécurité et erreurs

Enrôlement protégé, horloges synchronisées, limitation d’essais ; ne jamais journaliser graine/code.

## Validation

Tester fenêtre, code malformé, graine absente/corrompue et stockage chiffré.
