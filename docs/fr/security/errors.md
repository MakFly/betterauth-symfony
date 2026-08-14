# Erreurs d’authentification

## Objectif

Stabiliser la réponse client tout en gardant le contexte diagnostic côté serveur.

## Activation et configuration

L’authenticator protège les firewalls et l’API peut adopter `application/problem+json`.

## Contrat et flux

Expiration : `TokenExpiredException`; malformation, type ou vérification : `InvalidTokenException`; les deux deviennent 401 générique.

## Exemple

```json
{"type":"about:blank","title":"Unauthorized","status":401}
```

## Sécurité et erreurs

Journaliser classe, route et identifiant de requête, jamais jeton ou valeur à usage unique ; mapper aussi credentials invalides et limitations.

## Validation

Tester statuts, content-type, corrélation et absence de secrets.
