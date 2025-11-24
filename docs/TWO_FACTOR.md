# Two-Factor Authentication (TOTP)

BetterAuth Symfony intègre l'authentification à deux facteurs (2FA) basée sur TOTP (Time-based One-Time Password), compatible avec Google Authenticator, Authy, et autres applications d'authentification.

## Configuration

### Activation du 2FA

Dans `config/packages/better_auth.yaml` :

```yaml
better_auth:
    two_factor:
        enabled: true  # Active le 2FA
        issuer: 'MyApp'  # Nom affiché dans l'app d'authentification
        backup_codes_count: 10  # Nombre de codes de récupération
```

### Migration de la base de données

L'entité `TotpData` est automatiquement créée lors de l'installation. Assurez-vous que la migration a été exécutée :

```bash
php bin/console doctrine:migrations:migrate
```

## Routes API

### 1. Configuration du 2FA pour un utilisateur

**`POST /auth/2fa/setup`**

Configure le TOTP pour l'utilisateur actuellement connecté.

**Headers:**
```
Authorization: Bearer {access_token}
```

**Réponse:**
```json
{
    "secret": "JBSWY3DPEHPK3PXP",
    "qrCode": "data:image/png;base64,...",
    "backupCodes": [
        "12345678",
        "87654321",
        ...
    ],
    "uri": "otpauth://totp/MyApp:user@example.com?secret=JBSWY3DPEHPK3PXP&issuer=MyApp"
}
```

Le QR code peut être scanné directement avec une application d'authentification.

### 2. Validation du code TOTP après setup

**`POST /auth/2fa/validate`**

Valide que le code TOTP fonctionne correctement après la configuration initiale.

**Headers:**
```
Authorization: Bearer {access_token}
```

**Body:**
```json
{
    "code": "123456"
}
```

**Réponse:**
```json
{
    "success": true,
    "message": "2FA successfully enabled"
}
```

### 3. Vérification du code lors de la connexion

**`POST /auth/2fa/verify`**

Vérifie le code TOTP lors d'une connexion.

**Headers:**
```
Authorization: Bearer {access_token}
```

**Body:**
```json
{
    "code": "123456"
}
```

**Réponse:**
```json
{
    "success": true,
    "user": { ... }
}
```

### 4. Statut du 2FA

**`GET /auth/2fa/status`**

Vérifie si le 2FA est activé pour l'utilisateur.

**Headers:**
```
Authorization: Bearer {access_token}
```

**Réponse:**
```json
{
    "enabled": true,
    "verified": true
}
```

### 5. Désactivation du 2FA

**`POST /auth/2fa/disable`**

Désactive le 2FA pour l'utilisateur.

**Headers:**
```
Authorization: Bearer {access_token}
```

**Body:**
```json
{
    "code": "123456"
}
```

**Réponse:**
```json
{
    "success": true,
    "message": "2FA disabled"
}
```

### 6. Régénération des codes de récupération

**`POST /auth/2fa/backup-codes/regenerate`**

Génère de nouveaux codes de récupération (les anciens sont invalides).

**Headers:**
```
Authorization: Bearer {access_token}
```

**Body:**
```json
{
    "code": "123456"
}
```

**Réponse:**
```json
{
    "backupCodes": [
        "12345678",
        "87654321",
        ...
    ]
}
```

## Flow d'utilisation

### Configuration initiale

1. L'utilisateur se connecte et accède à `/auth/2fa/setup`
2. Le backend génère un secret TOTP et un QR code
3. L'utilisateur scanne le QR code avec Google Authenticator/Authy
4. L'utilisateur valide avec `/auth/2fa/validate` et un code de l'app
5. Le 2FA est maintenant actif

### Connexion avec 2FA activé

1. L'utilisateur se connecte normalement (`/auth/login`)
2. Si le 2FA est activé, le backend demande un code 2FA
3. L'utilisateur fournit le code via `/auth/2fa/verify`
4. L'accès est accordé

### Codes de récupération

Les codes de récupération permettent de se connecter si l'utilisateur perd l'accès à son application d'authentification. Ils sont fournis lors du setup initial et peuvent être régénérés.

## Applications d'authentification compatibles

- **Google Authenticator** (iOS, Android)
- **Authy** (iOS, Android, Desktop)
- **Microsoft Authenticator** (iOS, Android)
- **1Password** (toutes plateformes)
- **Bitwarden** (toutes plateformes)
- Toute application compatible TOTP (RFC 6238)

## Personnalisation

### Changer l'issuer

L'issuer est le nom affiché dans l'application d'authentification :

```yaml
better_auth:
    two_factor:
        issuer: 'MonEntreprise Production'
```

### Désactiver le 2FA globalement

```yaml
better_auth:
    two_factor:
        enabled: false
```

Les routes 2FA seront toujours disponibles mais retourneront une erreur.

## Sécurité

- Les secrets TOTP sont stockés de manière sécurisée en base de données
- Les codes de récupération sont hashés (comme des mots de passe)
- Les codes TOTP expirent après 30 secondes (standard TOTP)
- Protection contre les attaques par force brute (rate limiting recommandé)

## Exemple de frontend (Vue/React)

```typescript
// 1. Setup 2FA
async function setup2FA() {
  const response = await fetch('/auth/2fa/setup', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${accessToken}`,
    },
  });

  const data = await response.json();

  // Afficher le QR code
  document.getElementById('qrCode').src = data.qrCode;

  // Sauvegarder les codes de récupération
  console.log('Backup codes:', data.backupCodes);
}

// 2. Valider le code
async function validate2FA(code: string) {
  const response = await fetch('/auth/2fa/validate', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${accessToken}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ code }),
  });

  const data = await response.json();
  console.log('2FA enabled:', data.success);
}

// 3. Vérifier le code lors de la connexion
async function verify2FA(code: string) {
  const response = await fetch('/auth/2fa/verify', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${accessToken}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ code }),
  });

  const data = await response.json();
  console.log('Login successful:', data.success);
}
```

## Support

Pour toute question ou problème, consultez :
- [Documentation principale](../README.md)
- [Issues GitHub](https://github.com/betterauth/symfony/issues)
