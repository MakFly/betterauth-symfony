# Application de démonstration

## Objectif

Valider localement l’intégration v1.1 : interface, API JSON, persistance, courriel et styles.

## Activation et configuration

Depuis `examples/demo-app/`, lancez `composer install`, les migrations SQLite et la compilation Tailwind. Mailpit écoute sur `127.0.0.1:1025` (ou `infra-mailpit:1025`).

## Contrat et flux

L’application couvre inscription/connexion, `/api/me`, rotation/rejeu, déconnexion, TOTP, lien magique, réinitialisation, invité, appareil, monitoring et tenant.

## Matrice des routes

Les routes `/`, `/api/register`, `/api/login`, `/api/refresh`, `/api/logout`, `/api/me`, `/api/totp/*`, `/api/devices`, `/api/tenant/{tenant}`, `/api/magic-link/*`, `/api/password-reset/*` et `/api/guest/*` couvrent respectivement interface, session, ressources protégées et valeurs à usage unique.

## Exemple

| Domaine | Route(s) | Authentification |
| --- | --- | --- |
| Interface | `/` | publique |
| Session | `/api/register`, `/api/login`, `/api/refresh`, `/api/logout` | mixte |
| Protégé | `/api/me`, `/api/totp/*`, `/api/devices`, `/api/tenant/{tenant}` | access token |
| Usage unique | `/api/magic-link/*`, `/api/password-reset/*`, `/api/guest/*` | valeur opaque |

## Sécurité et erreurs

```bash
cd examples/demo-app
composer install
php bin/console doctrine:migrations:migrate --no-interaction
bun install && bun run build
php -S 127.0.0.1:8080 -t public
```

## Validation

SQLite conserve uniquement les hashes de refresh/usage unique et le ciphertext TOTP. Les réponses mail sont génériques et les erreurs API sont `application/problem+json`.
