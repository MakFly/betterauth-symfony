# Architecture

## Objectif

Le bundle fournit les primitives ; l’application reste autorité pour les utilisateurs et la politique métier.

## Activation et configuration

Configurer `better_auth`, brancher les ports activés et rattacher l’authenticator au firewall/provider voulu.

## Contrat et flux

Requête → extracteur → vérification PASETO → provider utilisateur → token Security Symfony → contrôleur. Les services optionnels utilisent des ports applicatifs.

## Exemple

```text
Authorization: Bearer <v4.local access token>
        -> parseAccessToken() -> loadUserByIdentifier() -> contrôleur protégé
```

## Sécurité et erreurs

Seuls `verify()` et `parseAccessToken()` autorisent ; `decode()` sert à l’inspection uniquement.

## Validation

Tracer une requête sans journaliser le jeton et tester chaque adaptateur configuré.
