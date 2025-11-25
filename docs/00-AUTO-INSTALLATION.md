# Installation Automatique

Ce document liste tous les fichiers et configurations installés **automatiquement** lors de l'exécution de `composer require betterauth/symfony-bundle`, **sans aucune intervention manuelle**.

## 📦 Fichiers Copiés par Symfony Flex

Lors de l'installation, Symfony Flex copie automatiquement les fichiers suivants depuis `recipes/1.0/config/` :

### 1. `config/packages/better_auth.yaml`

Configuration complète du bundle avec toutes les options documentées :

```yaml
better_auth:
    mode: 'api'
    secret: '%env(BETTER_AUTH_SECRET)%'
    session: { ... }
    token: { ... }
    oauth: { ... }
    multi_tenant: { ... }
    two_factor: { ... }
    security:
        auto_configure: true
        firewall_name: 'api'
        firewall_pattern: '^/api'
        public_routes_pattern: '^/auth'
    cors:
        auto_configure: true
    routing:
        auto_configure: true
        custom_controllers_namespace: 'App\Controller\Api'
    openapi:
        enabled: true
        path_prefix: ~  # Auto-détection depuis routes
```

### 2. `config/packages/security.yaml`

Configuration Symfony Security complète avec :

- **Password hashers** : Argon2id configuré pour BetterAuth
- **User provider** : `BetterAuthUserProvider` configuré
- **Firewalls** :
  - `dev` : Routes de développement (profiler, etc.)
  - `auth` : Routes publiques d'authentification (`^/auth`)
  - `api` : Routes API protégées (`^/api`)
  - `main` : Firewall principal (lazy)
- **Access control** : Règles pour routes publiques et protégées
- **Role hierarchy** : Hiérarchie des rôles (ROLE_ADMIN, etc.)

## 🔧 Variables d'Environnement

Deux variables sont ajoutées automatiquement dans `.env` :

```env
BETTER_AUTH_SECRET=generate-secret-key-here-64-chars
BETTER_AUTH_ISSUER=http://localhost:8000
```

> **⚠️ Important** : Vous devez générer un secret sécurisé et mettre à jour `BETTER_AUTH_SECRET` :
> ```bash
> php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
> ```

## ⚙️ Configuration Automatique via `BetterAuthExtension`

Le bundle configure automatiquement plusieurs composants Symfony via `prependExtensionConfig()` :

### Doctrine ORM (si Doctrine est installé)

- **Mapping automatique** des entités BetterAuth Core
- **Migrations** : Path des migrations BetterAuth ajouté automatiquement

### Security (si `security.auto_configure: true` - **DÉFAUT**)

Configuration automatique de `security.yaml` :

- **Provider** : `better_auth_provider` avec `BetterAuthUserProvider`
- **Firewalls** :
  - `better_auth_public` : Routes publiques d'authentification
  - `api` : Routes API protégées (ou version spécifique si `/api/v1/auth`)
- **Access control** :
  - Routes publiques : `/auth/(register|login|refresh|...)`
  - Routes protégées : `/auth/*` et `/api/*`
- **Détection automatique** des patterns (ex: `/api/v1/auth` → firewall `api_v1`)

### CORS (si `nelmio/cors-bundle` installé ET `cors.auto_configure: true` - **DÉFAUT**)

Configuration automatique de `nelmio_cors.yaml` :

- Patterns pour routes auth (`^/auth`)
- Patterns pour routes API (`^/api`)

### Routing (si `routing.auto_configure: true` - **DÉFAUT**)

Configuration automatique de `routes.yaml` :

- Préfixe automatique pour `App\Controller\Api`
- Détection du préfixe depuis `security.public_routes_pattern`

Exemple : Si `public_routes_pattern: '^/api/v1/auth'`, le préfixe `/api/v1` est automatiquement ajouté aux controllers dans `App\Controller\Api`.

### OpenAPI (si `api-platform` installé ET `openapi.enabled: true` - **DÉFAUT**)

Documentation automatique des endpoints d'authentification :

- **Détection dynamique** du préfixe depuis le Router Symfony
- **Endpoints ajoutés** :
  - `POST /auth/register`
  - `POST /auth/login`
  - `GET /auth/me`
  - `POST /auth/refresh`
  - `POST /auth/logout`
  - `POST /auth/revoke-all`
  - `GET /auth/oauth/{provider}`
  - `GET /auth/oauth/{provider}/callback`
- **Tags** : "Authentication" et "OAuth"
- **Security scheme** : Bearer (Paseto V4)

## 🔌 Services Auto-Configurés

Tous les services BetterAuth sont automatiquement configurés via `config/services.yaml` du bundle :

- `BetterAuth\Core\PasswordHasher`
- `BetterAuth\Core\TokenService`
- `BetterAuth\Core\Config\AuthConfig`
- `BetterAuth\Core\Plugin\PluginManager`
- `BetterAuth\Symfony\Plugin\SymfonyPluginLoader`
- `BetterAuth\Core\SessionService`
- `BetterAuth\Core\SessionAuthManager`
- `BetterAuth\Core\TokenAuthManager`
- `BetterAuth\Core\HybridAuthManager`
- `BetterAuth\Symfony\Security\BetterAuthUserProvider`
- `BetterAuth\Symfony\Security\BetterAuthAuthenticator`
- `BetterAuth\Symfony\OpenApi\AuthenticationDecorator`

## 🔄 Auto-Configuration des Entités (`EntityAutoConfigurationPass`)

Le bundle détecte automatiquement les entités `App\Entity\*` et configure les repositories pour les utiliser :

| Repository | Entité détectée |
|------------|-----------------|
| `DoctrineUserRepository` | `App\Entity\User` |
| `DoctrineSessionRepository` | `App\Entity\Session` |
| `DoctrineRefreshTokenRepository` | `App\Entity\RefreshToken` |
| `DoctrineMagicLinkRepository` | `App\Entity\MagicLinkToken` |
| `DoctrineEmailVerificationRepository` | `App\Entity\EmailVerificationToken` |
| `DoctrinePasswordResetRepository` | `App\Entity\PasswordResetToken` |
| `DoctrineTotpRepository` | `App\Entity\TotpData` |

**Aucune configuration manuelle dans `services.yaml` n'est nécessaire !**

Si les classes `App\Entity\*` existent (générées par `better-auth:install`), le bundle les utilise automatiquement. Sinon, il utilise les classes par défaut du bundle.

## 📦 Bundle Enregistré

Le bundle `BetterAuth\Symfony\BetterAuthBundle` est automatiquement enregistré dans `config/bundles.php`.

## ✅ Résumé : Ce qui est fait automatiquement

- ✅ 2 fichiers de configuration copiés (`better_auth.yaml`, `security.yaml`)
- ✅ 2 variables d'environnement ajoutées (`.env`)
- ✅ Doctrine ORM mapping configuré automatiquement
- ✅ Security.yaml configuré automatiquement (firewalls, providers, access_control)
- ✅ CORS configuré automatiquement (si `nelmio/cors-bundle` présent)
- ✅ Routing configuré automatiquement (préfixe pour controllers)
- ✅ OpenAPI documentation configurée automatiquement (si `api-platform` présent)
- ✅ Tous les services BetterAuth auto-configurés
- ✅ Bundle enregistré dans `bundles.php`

## ⚠️ Ce qui n'est PAS fait automatiquement

Ces étapes nécessitent l'exécution de `php bin/console better-auth:install` :

- ❌ **Entités** (src/Entity/) :
  - `User.php` - Entité utilisateur
  - `Session.php` - Gestion des sessions
  - `RefreshToken.php` - Tokens de rafraîchissement
  - `MagicLinkToken.php` - Liens magiques (passwordless)
  - `EmailVerificationToken.php` - Vérification email
  - `PasswordResetToken.php` - Réinitialisation mot de passe
  - `TotpData.php` - Données 2FA/TOTP
  - `GuestSession.php` - Sessions invité (optionnel)

- ❌ **Controllers** (src/Controller/Api/) :
  
  **Core Controllers (installés par `better-auth:install`) :**
  - `Trait/ApiResponseTrait.php` - Trait pour réponses API uniformes
  - `AuthController.php` - Endpoints auth + 2FA (11 endpoints)
  - `PasswordController.php` - Endpoints mot de passe (3 endpoints)
  - `SessionsController.php` - Endpoints sessions (2 endpoints)
  
  **Optional Controllers (via `better-auth:add-controller`) :**
  - `OAuthController.php` - OAuth providers (3 endpoints)
  - `EmailVerificationController.php` - Vérification email (4 endpoints)
  - `MagicLinkController.php` - Authentification passwordless (3 endpoints)
  - `GuestSessionController.php` - Sessions invité (4 endpoints)
  - `AccountLinkController.php` - Liaison comptes tiers (4 endpoints)
  - `DeviceController.php` - Gestion appareils (6 endpoints)

- ❌ **Migrations** :
  - Migrations Doctrine pour toutes les tables

- ❌ **Configuration manuelle de `security.yaml`** (si `auto_configure: false`)

## 🚀 Après l'installation

1. **Générer un secret sécurisé** :
   ```bash
   php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
   ```

2. **Mettre à jour `BETTER_AUTH_SECRET` dans `.env`** avec le secret généré

3. **(Optionnel) Lancer `better-auth:install`** pour générer les entités :
   ```bash
   php bin/console better-auth:install
   ```

4. **(Optionnel) Ajouter des controllers supplémentaires** :
   ```bash
   # Lister les controllers disponibles
   php bin/console better-auth:add-controller --list
   
   # Ajouter un controller spécifique
   php bin/console better-auth:add-controller oauth
   
   # Ajouter tous les controllers
   php bin/console better-auth:add-controller --all
   ```

## 📚 Voir aussi

- [Installation Guide](01-INSTALLATION.md)
- [Configuration Reference](02-CONFIGURATION.md)
- [API Platform Integration](../README.md#-api-platform-integration)

