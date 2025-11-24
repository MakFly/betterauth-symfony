# Gestion des Sessions par Mode d'Authentification

## Résumé

La gestion des sessions et devices dépend du mode d'authentification configuré dans `better_auth.yaml` :

- **Mode `api`** : Tokens JWT/Paseto stateless uniquement
- **Mode `session`** : Sessions stateful stockées en base de données
- **Mode `hybrid`** : Supporte les deux (tokens + sessions)

## Mode `api` (Stateless)

### Caractéristiques
- ✅ **Tokens JWT/Paseto** : Access tokens (2h) + Refresh tokens (30j)
- ✅ **Stateless** : Pas de stockage serveur pour les access tokens
- ✅ **Scalable** : Pas de dépendance à une base de données partagée
- ❌ **Pas de sessions** : Pas de concept de "session" ou "device" au sens classique
- ❌ **Pas de révocation immédiate** : Les access tokens restent valides jusqu'à expiration

### Gestion disponible

#### ✅ Disponible
- `POST /auth/refresh` - Rafraîchir un access token
- `POST /auth/revoke-all` - Révoquer tous les refresh tokens (déconnecte tous les devices)

#### ❌ Non disponible
- `GET /auth/sessions` - Liste des sessions (uniquement en mode session/hybrid)
- `DELETE /auth/sessions/{id}` - Révoquer une session spécifique (uniquement en mode session/hybrid)

### Refresh Tokens comme "Sessions"

En mode `api`, les **refresh tokens** stockés en base peuvent être considérés comme des "sessions" :

```php
// Les refresh tokens sont stockés dans la table refresh_tokens
// Chaque refresh token = un "device" connecté
// Mais il n'y a pas d'endpoint pour les lister individuellement
```

**Limitation actuelle** : Il n'y a pas d'endpoint pour lister les refresh tokens actifs en mode `api`.

## Mode `session` (Stateful)

### Caractéristiques
- ✅ **Sessions stockées en base** : Table `sessions` avec métadonnées (IP, User-Agent, etc.)
- ✅ **Révocation immédiate** : Suppression de la session = déconnexion immédiate
- ✅ **Gestion complète** : Liste, révocation sélective, tracking des devices
- ❌ **Stateful** : Nécessite une base de données partagée
- ❌ **Moins scalable** : Dépend de la performance de la base de données

### Gestion disponible

#### ✅ Disponible
- `GET /auth/sessions` - Liste toutes les sessions actives
- `DELETE /auth/sessions/{id}` - Révoquer une session spécifique
- `POST /auth/logout` - Déconnexion (supprime la session actuelle)
- `POST /auth/revoke-all` - Révoquer toutes les sessions

### Exemple de réponse `/auth/sessions`

```json
{
  "sessions": [
    {
      "id": "session_token_123",
      "device": "Desktop",
      "browser": "Chrome",
      "os": "Windows",
      "ip": "192.168.1.1",
      "location": "Paris, France",
      "current": true,
      "createdAt": "2024-11-24 10:00:00",
      "lastActiveAt": "2024-11-24 16:30:00",
      "expiresAt": "2024-12-01 10:00:00"
    }
  ]
}
```

## Mode `hybrid` (Recommandé)

### Caractéristiques
- ✅ **Les deux modes** : Supporte tokens ET sessions
- ✅ **Flexibilité maximale** : Choisir le meilleur mode selon le cas d'usage
- ✅ **Gestion complète** : Toutes les fonctionnalités des deux modes

### Gestion disponible

#### ✅ Disponible (comme mode session)
- `GET /auth/sessions` - Liste toutes les sessions actives
- `DELETE /auth/sessions/{id}` - Révoquer une session spécifique
- `POST /auth/logout` - Déconnexion
- `POST /auth/revoke-all` - Révoquer toutes les sessions/tokens

#### ✅ Disponible (comme mode api)
- `POST /auth/refresh` - Rafraîchir un access token

## Recommandations

### Pour une API REST pure (mobile, SPA)
```yaml
better_auth:
    mode: 'api'  # Stateless, scalable
```

**Avantages** :
- Pas de dépendance à une base de données pour les access tokens
- Scalable horizontalement
- Parfait pour les microservices

**Inconvénients** :
- Pas de gestion granulaire des "sessions"
- Pas de révocation immédiate des access tokens

### Pour une application web traditionnelle
```yaml
better_auth:
    mode: 'session'  # Stateful, contrôle total
```

**Avantages** :
- Gestion complète des sessions
- Révocation immédiate
- Tracking des devices

**Inconvénients** :
- Nécessite une base de données partagée
- Moins scalable

### Pour une application moderne (web + mobile)
```yaml
better_auth:
    mode: 'hybrid'  # Le meilleur des deux mondes
```

**Avantages** :
- Flexibilité maximale
- Supporte tous les cas d'usage
- Gestion complète des sessions

**Inconvénients** :
- Légèrement plus complexe

## Conclusion

**En mode `api`** :
- ❌ Pas de gestion de sessions/devices au sens classique
- ✅ Utilise `revokeAllTokens()` pour déconnecter tous les devices
- ⚠️ Les refresh tokens peuvent être considérés comme des "sessions", mais il n'y a pas d'endpoint pour les lister

**En mode `session` ou `hybrid`** :
- ✅ Gestion complète des sessions/devices
- ✅ Liste, révocation sélective, tracking complet

**Recommandation** : Utilisez le mode `hybrid` pour avoir toutes les fonctionnalités disponibles.

