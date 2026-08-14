# Configuration de sécurité

## Objectif

Rendre explicites les choix de jetons, d’extraction et de fonctionnalités.

## Activation et configuration

```yaml
better_auth:
    secret: '%env(BETTER_AUTH_SECRET)%'
    user_id_claim: sub
    access_token: { ttl: 3600 }
    refresh_token: { enabled: true, ttl: 2592000, store: App\Security\RefreshTokenStore }
    token_extractors: { authorization_header: { enabled: true }, cookie: { enabled: false } }
```
Voir la [référence de configuration](../../CONFIGURATION.md).

## Contrat et flux

Symfony charge l’arbre et l’extension branche les services ; l’application fournit provider, contrôleurs, stores et transports.

## Exemple

```dotenv
BETTER_AUTH_SECRET=valeur-du-gestionnaire-de-secrets-de-32-octets-minimum
```

## Sécurité et erreurs

Hors dépôt pour les secrets ; HTTPS, cookies sûrs, CSRF, limitations de débit et masquage des logs sont requis.

## Validation

Lancer `php bin/console lint:yaml config`, vider le cache et tester chaque flux activé.
