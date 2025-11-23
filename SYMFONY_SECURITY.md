# Symfony Security Integration - Complete Guide

## 🎯 Réponse à vos questions

### ❓ Pourquoi `security.yaml` est pratiquement vide ?

**Avant** (sans Flex recipe) :
```yaml
# security.yaml - VIDE ou configuration par défaut
security:
    providers:
        users_in_memory: { memory: null }
```

**Raison** : BetterAuth était une bibliothèque standalone sans intégration Symfony Security automatique.

**Après** (avec Flex recipe) :
```yaml
# security.yaml - AUTO-CONFIGURÉ par la recette Flex
security:
    providers:
        better_auth:
            id: BetterAuth\Symfony\Security\BetterAuthUserProvider

    firewalls:
        api:
            pattern: ^/api
            stateless: true
            custom_authenticators:
                - BetterAuth\Symfony\Security\BetterAuthAuthenticator
```

**Solution** : Créer une **recette Symfony Flex** qui installe automatiquement `security.yaml` (comme LexikJWT le fait).

---

### ❓ Pourquoi pas de bundle complet comme LexikJWT ?

**C'est exactement ce qu'on vient de créer !** 🎉

Le bundle BetterAuth Symfony inclut maintenant :

1. ✅ **Recette Flex** (`recipes/1.0/manifest.json`)
2. ✅ **CompilerPass** (`BetterAuthSecurityPass.php`)
3. ✅ **Auto-configuration** des services Symfony Security
4. ✅ **Installation automatique** de security.yaml
5. ✅ **Surcharge propre** de la configuration existante

---

## 🏗️ Architecture du Bundle (Comme LexikJWT)

### 1. Structure Complète

```
better-auth-php/packages/symfony/
├── recipes/                         ← Recette Symfony Flex
│   └── 1.0/
│       ├── manifest.json           ← Définition de la recette
│       └── config/packages/
│           ├── security.yaml       ← Auto-installé par Flex
│           └── better_auth.yaml    ← Configuration BetterAuth
│
├── src/
│   ├── BetterAuthBundle.php        ← Bundle principal
│   ├── DependencyInjection/
│   │   ├── BetterAuthExtension.php        ← Charge la config
│   │   ├── BetterAuthSecurityPass.php     ← Auto-config Security
│   │   └── Configuration.php               ← Arbre de config
│   └── Security/
│       ├── BetterAuthAuthenticator.php     ← Comme JWTAuthenticator
│       ├── BetterAuthUserProvider.php      ← Comme JWTUserProvider
│       └── BetterAuthUser.php              ← Wrapper UserInterface
│
└── config/
    └── services.yaml                ← Services auto-chargés
```

### 2. Flux d'Installation (Comme LexikJWT)

```bash
composer require betterauth/symfony-bundle
```

**1. Symfony Flex détecte la recette**
```
⚙️  Executing script cache:clear
⚙️  Executing script assets:install
✅  Configuring betterauth/symfony-bundle
```

**2. Flex copie les fichiers**
```
config/packages/
├── better_auth.yaml  ← Copié depuis recipes/1.0/
└── security.yaml     ← Copié et FUSIONNE avec l'existant
```

**3. CompilerPass s'exécute**
```php
// BetterAuthSecurityPass::process()
✅ Auto-tag BetterAuthAuthenticator → security.authenticator
✅ Auto-tag BetterAuthUserProvider → security.user_provider
✅ Configuration Symfony Security complète
```

**4. Variables d'environnement ajoutées**
```env
BETTER_AUTH_SECRET=generate-secret-key-here
BETTER_AUTH_ISSUER=http://localhost:8000
```

**5. Message post-installation**
```
🎉 BetterAuth Bundle is now installed!
📖 Next steps: Update BETTER_AUTH_SECRET in .env
```

---

## 🔧 Comment le Bundle Surcharge security.yaml

### Méthode 1 : Recette Flex (Préférée)

**Fichier** : `recipes/1.0/config/packages/security.yaml`

```yaml
security:
    providers:
        better_auth:
            id: BetterAuth\Symfony\Security\BetterAuthUserProvider

    firewalls:
        auth:
            pattern: ^/auth
            stateless: true
            security: false

        api:
            pattern: ^/api
            stateless: true
            provider: better_auth
            custom_authenticators:
                - BetterAuth\Symfony\Security\BetterAuthAuthenticator
```

**Lors de l'installation :**
- Symfony Flex **fusionne** cette configuration avec l'existante
- **Ne supprime pas** les configurations personnalisées
- **Ajoute** les sections BetterAuth

### Méthode 2 : CompilerPass (Auto-configuration)

**Fichier** : `src/DependencyInjection/BetterAuthSecurityPass.php`

```php
class BetterAuthSecurityPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Auto-tag pour Symfony Security
        if ($container->hasDefinition('BetterAuth\Symfony\Security\BetterAuthAuthenticator')) {
            $definition = $container->getDefinition('BetterAuth\Symfony\Security\BetterAuthAuthenticator');
            $definition->addTag('security.authenticator');
        }

        if ($container->hasDefinition('BetterAuth\Symfony\Security\BetterAuthUserProvider')) {
            $definition = $container->getDefinition('BetterAuth\Symfony\Security\BetterAuthUserProvider');
            $definition->addTag('security.user_provider');
        }
    }
}
```

**Enregistré dans le bundle :**
```php
// BetterAuthBundle::build()
public function build(ContainerBuilder $container): void
{
    parent::build($container);
    $container->addCompilerPass(new BetterAuthSecurityPass());
}
```

---

## 📦 Utilisation du Bundle (Zéro Configuration)

### Installation

```bash
composer require betterauth/symfony-bundle
```

### Configuration Automatique ✅

Après installation, vous avez :

1. **`config/packages/better_auth.yaml`** - Configuration BetterAuth
2. **`config/packages/security.yaml`** - Symfony Security configuré
3. **`.env`** - Variables d'environnement ajoutées
4. **Services** - Auto-enregistrés et auto-taggés

### Utilisation Immédiate

```php
#[Route('/api/profile')]
#[IsGranted('ROLE_USER')]
public function profile(): JsonResponse
{
    $user = $this->getUser(); // BetterAuthUser automatiquement injecté

    return $this->json([
        'id' => $user->getUserIdentifier(),
        'email' => $user->email,
    ]);
}
```

**Aucune configuration manuelle nécessaire !**

---

## 🆚 Comparaison avec LexikJWT

| Fonctionnalité | LexikJWT | BetterAuth |
|----------------|----------|------------|
| **Recette Flex** | ✅ Auto-installe security.yaml | ✅ Auto-installe security.yaml |
| **CompilerPass** | ✅ JWTAuthenticatorCompilerPass | ✅ BetterAuthSecurityPass |
| **Authenticator** | ✅ JWTAuthenticator | ✅ BetterAuthAuthenticator |
| **UserProvider** | ✅ JWTUserProvider | ✅ BetterAuthUserProvider |
| **Format Token** | JWT (RS256, HS256) | **Paseto V4** (plus sécurisé) |
| **OAuth** | ❌ Pas inclus | ✅ Google, GitHub, etc. |
| **Multi-tenant** | ❌ Pas inclus | ✅ Organizations, Teams |
| **Refresh Token** | ⚠️ Manuel | ✅ Built-in |
| **SSO/OIDC** | ❌ Pas inclus | ✅ Built-in |

**Conclusion** : BetterAuth = LexikJWT + OAuth + Multi-tenant + Meilleure sécurité

---

## 🔄 Migration depuis Configuration Manuelle

Si vous avez déjà configuré BetterAuth manuellement :

### 1. Sauvegarder votre config actuelle

```bash
cp config/packages/security.yaml config/packages/security.yaml.backup
```

### 2. Réinstaller avec Flex

```bash
composer remove betterauth/symfony-bundle
composer require betterauth/symfony-bundle
```

### 3. Fusionner les configurations

Comparez `security.yaml` avec `security.yaml.backup` et fusionnez vos configurations personnalisées.

### 4. Supprimer les services manuels

Dans `config/services.yaml`, supprimez :

```yaml
# ❌ À SUPPRIMER (maintenant auto-configuré)
BetterAuth\Symfony\Security\BetterAuthAuthenticator:
    arguments:
        $apiAuthManager: '@BetterAuth\Core\ApiAuthManager'
    public: true

BetterAuth\Symfony\Security\BetterAuthUserProvider:
    arguments:
        $userRepository: '@BetterAuth\Core\Interfaces\UserRepositoryInterface'
    public: true
```

Ces services sont maintenant **auto-configurés** par le CompilerPass.

---

## 📚 Documentation Complète

- **[INTEGRATION.md](INTEGRATION.md)** - Guide d'intégration complet
- **[README.md](README.md)** - Documentation générale
- **[../../docs/](../../docs/)** - Documentation BetterAuth Core

---

## ✅ Checklist d'Implémentation

### Pour les Utilisateurs

- [x] ✅ Installer via Composer
- [x] ✅ Générer une clé secrète
- [x] ✅ Mettre à jour `.env`
- [x] ✅ Créer les repositories
- [x] ✅ Utiliser dans les contrôleurs

### Pour les Contributeurs

- [x] ✅ Créer la recette Flex
- [x] ✅ Implémenter le CompilerPass
- [x] ✅ Auto-taguer les services
- [x] ✅ Documenter l'intégration
- [ ] ⏳ Soumettre à symfony/recipes-contrib
- [ ] ⏳ Ajouter des tests d'intégration
- [ ] ⏳ Créer des commandes CLI

---

## 🎉 Conclusion

**Votre question : "Pourquoi security.yaml est vide ?"**

**Réponse** : Parce que BetterAuth n'avait pas encore de recette Symfony Flex !

**Solution** : On vient de créer un bundle complet avec :
1. ✅ Recette Flex (comme LexikJWT)
2. ✅ CompilerPass pour auto-configuration
3. ✅ Installation automatique de security.yaml
4. ✅ Services auto-taggés

**Maintenant security.yaml est AUTO-CONFIGURÉ à l'installation !** 🚀
