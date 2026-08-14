# Réinitialisation du mot de passe

## Objectif

Récupérer un compte par preuve email courte et invalider ses sessions.

## Activation et configuration

Activer `features.email_reset`, fournir store, mailer et hasher ; contrôleur et entité restent applicatifs.

## Contrat et flux

Émettre purpose `email_reset`, consommer atomiquement, hacher le nouveau mot de passe puis `revokeAll($userId)`.

## Exemple

```php
$value = $resetTokens->issue($id);
$user->setPassword($hasher->hashPassword($user, $password));
$refresh->revokeAll($id);
```

## Sécurité et erreurs

Réponse identique pour adresse connue/inconnue, rate limits, jamais de valeur brute et invalidation après succès.

## Validation

Tester usage unique, expiration, mauvais purpose, hash et révocation.
