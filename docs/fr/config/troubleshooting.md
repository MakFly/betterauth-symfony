# Dépannage

## Objectif

Diagnostiquer sans exposer identifiants ni payloads.

## Activation et configuration

Vérifier `BETTER_AUTH_SECRET`, firewall/provider et configuration identique sur chaque instance.

## Contrat et flux

Une requête extrait/vérifie puis charge l’utilisateur ; la rotation consomme atomiquement un hash et TOTP déchiffre sa graine.

## Exemple

```bash
php bin/console debug:config better_auth
php bin/console cache:clear
```

## Sécurité et erreurs

401 permanent : syntaxe Bearer, secret/issuer, claim et provider. Refresh invalide : hash, expiration, révocation, transaction. Rejeu : révoquer toutes les sessions. TOTP : ciphertext et horloge.

## Validation

Reproduire sur fixture jetable, relever statuts/identifiant de requête, puis supprimer la fixture. Ne jamais activer les logs de payload en production.
