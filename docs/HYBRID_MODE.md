# Mode Hybrid : API + Session

Le mode **hybrid** permet d'utiliser à la fois l'authentification par tokens JWT (API stateless) et par sessions (stateful cookies) dans la même application.

## Configuration

Dans `config/packages/better_auth.yaml` :

```yaml
better_auth:
    mode: 'hybrid'  # Supporte API tokens + Session cookies

    session:
        lifetime: 604800  # 7 jours pour Magic Link
        cookie_name: 'better_auth_session'

    token:
        lifetime: 7200           # 2 heures pour JWT
        refresh_lifetime: 2592000  # 30 jours
```

## Modes disponibles

### 1. Mode `api` (Stateless)
- **Utilise** : JWT tokens uniquement
- **Authentification** : `Authorization: Bearer {token}`
- **Avantages** : Scalable, pas de stockage serveur
- **Inconvénients** : Pas de révocation immédiate des tokens

### 2. Mode `session` (Stateful)
- **Utilise** : Cookies de session uniquement
- **Authentification** : Cookie `better_auth_session`
- **Avantages** : Révocation immédiate, contrôle total
- **Inconvénients** : Nécessite du stockage serveur

### 3. Mode `hybrid` ⭐ (Recommandé)
- **Utilise** : Les deux (tokens JWT + cookies)
- **Authentification** : Header OU Cookie (le backend accepte les deux)
- **Avantages** : Maximum de flexibilité, meilleur des deux mondes
- **Cas d'usage** :
  - **Magic Link** → Session cookie (persiste après refresh)
  - **Login classique** → JWT tokens (API stateless)
  - **Mobile apps** → JWT tokens
  - **Web apps** → Cookies ou tokens au choix

## Comment ça fonctionne

### Backend (Symfony)

Le backend accepte **les deux** types d'authentification :

```php
// AuthController.php
private function getAuthToken(Request $request): ?string
{
    // 1. Essaye le Bearer token (API mode)
    $token = $this->getBearerToken($request);
    if ($token) {
        return $token;
    }

    // 2. Essaye le cookie access_token (Session/Hybrid mode)
    $token = $request->cookies->get('access_token');
    if ($token) {
        return $token;
    }

    return null;
}
```

### Frontend (React/Vue/Angular)

Le frontend peut choisir comment s'authentifier :

#### Option 1 : Magic Link (Cookie)
```typescript
// Le Magic Link retourne des tokens
const response = await magicLinkApi.verify(token);
const { access_token, refresh_token } = response.data;

// Stocke dans les cookies
setCookie('access_token', access_token, 1);
setCookie('refresh_token', refresh_token, 7);

// Le backend lira automatiquement les cookies !
```

#### Option 2 : Login classique (Header OU Cookie)
```typescript
// Login classique
const response = await authApi.login(email, password);
const { access_token } = response.data;

// Choix 1: Header (API pur)
axios.defaults.headers.common['Authorization'] = `Bearer ${access_token}`;

// Choix 2: Cookie (comme Magic Link)
setCookie('access_token', access_token, 1);
```

## Cas d'usage pratiques

### Cas 1 : Application web avec Magic Link

```yaml
# Configuration
better_auth:
    mode: 'hybrid'
```

**Flux :**
1. Utilisateur clique sur Magic Link dans l'email
2. Backend génère des tokens JWT ET crée une session
3. Frontend stocke les tokens dans les **cookies**
4. Les cookies sont **automatiquement envoyés** à chaque requête
5. ✅ L'utilisateur reste connecté même après refresh !

### Cas 2 : Application mobile + Web

```yaml
# Configuration
better_auth:
    mode: 'hybrid'
```

**Mobile (API pure) :**
```typescript
// Stocke le token en mémoire ou secure storage
localStorage.setItem('access_token', token);

// Envoie dans le header
headers: { Authorization: `Bearer ${token}` }
```

**Web (Session) :**
```typescript
// Stocke dans les cookies
document.cookie = `access_token=${token}`;

// Pas besoin de header, les cookies sont auto-envoyés
fetch('/api/me'); // ✅ Fonctionne !
```

### Cas 3 : API publique + Dashboard admin

```yaml
# Configuration
better_auth:
    mode: 'hybrid'
```

**API publique :**
- Les développeurs utilisent des tokens API (`Authorization: Bearer`)
- Tokens long-lived, révocables via dashboard

**Dashboard admin :**
- Les admins utilisent Magic Link ou login classique
- Sessions avec cookies pour une UX fluide

## Sécurité

### CORS et Cookies

Pour que les cookies fonctionnent avec un frontend séparé :

```yaml
# config/packages/nelmio_cors.yaml
nelmio_cors:
    defaults:
        origin_regex: true
        allow_origin: ['%env(FRONTEND_URL)%']
        allow_credentials: true  # ⚠️ Important pour les cookies !
        allow_headers: ['*']
        allow_methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']
```

```typescript
// Frontend
axios.create({
  baseURL: 'http://localhost:8000',
  withCredentials: true  // ⚠️ Important pour envoyer les cookies !
})
```

### SameSite et HTTPS

```php
// En production
setCookie('access_token', $token, [
    'httponly' => true,
    'secure' => true,      // Uniquement HTTPS
    'samesite' => 'strict', // Protection CSRF
]);
```

## Avantages du mode hybrid

✅ **Flexibilité maximale** : Supporte tous les cas d'usage
✅ **Compatibilité** : Fonctionne avec web, mobile, API publiques
✅ **UX optimale** : Magic Link + refresh sans déconnexion
✅ **Sécurité** : Révocation immédiate des sessions si nécessaire
✅ **Scalabilité** : Les tokens JWT ne requièrent pas de stockage serveur

## Migration

### De `api` vers `hybrid`

```yaml
# Avant
better_auth:
    mode: 'api'

# Après
better_auth:
    mode: 'hybrid'
```

✅ **Pas de breaking change !** Les tokens API continuent de fonctionner.
✨ **Bonus** : Les cookies sont maintenant aussi supportés.

### De `session` vers `hybrid`

```yaml
# Avant
better_auth:
    mode: 'session'

# Après
better_auth:
    mode: 'hybrid'
```

✅ **Pas de breaking change !** Les sessions continuent de fonctionner.
✨ **Bonus** : Les tokens API sont maintenant aussi supportés.

## Conclusion

Le mode **hybrid** est le choix recommandé pour la plupart des applications modernes. Il offre la flexibilité de l'API stateless avec le confort des sessions stateful, le tout sans compromis sur la sécurité.

🚀 **Utilisez `hybrid` par défaut, sauf si vous avez une raison spécifique de ne pas le faire !**
