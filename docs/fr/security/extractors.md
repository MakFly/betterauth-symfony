# Extracteurs de jeton

## Objectif

Accepter un jeton seulement depuis une frontière explicitement configurée.

## Activation et configuration

```yaml
better_auth:
    token_extractors:
        authorization_header: { enabled: true, max_length: 8192 }
        cookie: { enabled: false, name: access_token, max_length: 8192 }
```

## Contrat et flux

L’extracteur header lit `Authorization: Bearer <token>` ; celui du cookie lit le nom configuré ; la chaîne renvoie la première valeur.

## Exemple

```http
GET /api/me HTTP/1.1
Authorization: Bearer v4.local....
```

## Sécurité et erreurs

Rejeter valeur absente, malformée, avec espaces ou trop longue. Les cookies exigent HTTPS, Secure, HttpOnly/SameSite adaptés et CSRF.

## Validation

Tester priorité, extracteurs désactivés, longueur et requête sans jeton.
