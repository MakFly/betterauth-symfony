# Two-Factor Authentication (TOTP)

Add an extra layer of security with Time-based One-Time Passwords (TOTP).

## Overview

BetterAuth supports TOTP-based 2FA compatible with:
- Google Authenticator
- Authy
- Microsoft Authenticator
- 1Password
- Bitwarden
- Any TOTP-compatible app (RFC 6238)

## Configuration

```yaml
# config/packages/better_auth.yaml
better_auth:
    two_factor:
        enabled: true
        issuer: 'MyApp'           # Name shown in authenticator apps
        backup_codes_count: 10    # Number of recovery codes
```

---

## API Endpoints

### 1. Setup 2FA

**POST /auth/2fa/setup**

Initialize 2FA for the authenticated user.

```bash
curl -X POST http://localhost:8000/auth/2fa/setup \
  -H "Authorization: Bearer {access_token}"
```

**Response:**
```json
{
  "secret": "JBSWY3DPEHPK3PXP",
  "qrCode": "data:image/png;base64,...",
  "manualEntryKey": "JBSWY3DPEHPK3PXP",
  "backupCodes": [
    "12345678",
    "87654321",
    "..."
  ]
}
```

### 2. Validate Setup

**POST /auth/2fa/validate**

Confirm 2FA setup with a code from the authenticator app.

```bash
curl -X POST http://localhost:8000/auth/2fa/validate \
  -H "Authorization: Bearer {access_token}" \
  -H "Content-Type: application/json" \
  -d '{"code": "123456"}'
```

**Response:**
```json
{
  "message": "2FA successfully enabled",
  "enabled": true
}
```

### 3. Login with 2FA

**POST /auth/login/2fa**

Complete login when 2FA is required.

```bash
curl -X POST http://localhost:8000/auth/login/2fa \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password123",
    "code": "123456"
  }'
```

**Response:**
```json
{
  "access_token": "v4.local.eyJ...",
  "refresh_token": "rt_abc123...",
  "user": { ... }
}
```

### 4. Check 2FA Status

**GET /auth/2fa/status**

Check if 2FA is enabled for the user.

```bash
curl -X GET http://localhost:8000/auth/2fa/status \
  -H "Authorization: Bearer {access_token}"
```

**Response:**
```json
{
  "enabled": true,
  "backupCodesRemaining": 8
}
```

### 5. Disable 2FA

**POST /auth/2fa/disable**

Disable 2FA (requires current TOTP code).

```bash
curl -X POST http://localhost:8000/auth/2fa/disable \
  -H "Authorization: Bearer {access_token}" \
  -H "Content-Type: application/json" \
  -d '{"code": "123456"}'
```

**Response:**
```json
{
  "message": "2FA disabled",
  "enabled": false
}
```

### 6. Regenerate Backup Codes

**POST /auth/2fa/backup-codes/regenerate**

Generate new backup codes (invalidates old ones).

```bash
curl -X POST http://localhost:8000/auth/2fa/backup-codes/regenerate \
  -H "Authorization: Bearer {access_token}" \
  -H "Content-Type: application/json" \
  -d '{"code": "123456"}'
```

**Response:**
```json
{
  "message": "Backup codes regenerated",
  "backupCodes": [
    "11111111",
    "22222222",
    "..."
  ]
}
```

---

## Authentication Flow

### Setup Flow

```
┌──────────┐                                    ┌──────────┐
│   User   │                                    │  Server  │
└────┬─────┘                                    └────┬─────┘
     │                                               │
     │  POST /auth/2fa/setup                        │
     ├──────────────────────────────────────────────►
     │                                               │
     │  {secret, qrCode, backupCodes}               │
     ◄──────────────────────────────────────────────┤
     │                                               │
     │  [User scans QR code]                        │
     │                                               │
     │  POST /auth/2fa/validate                     │
     │  {code: "123456"}                            │
     ├──────────────────────────────────────────────►
     │                                               │
     │  {enabled: true}                              │
     ◄──────────────────────────────────────────────┤
```

### Login Flow with 2FA

```
┌──────────┐                                    ┌──────────┐
│   User   │                                    │  Server  │
└────┬─────┘                                    └────┬─────┘
     │                                               │
     │  POST /auth/login                            │
     │  {email, password}                           │
     ├──────────────────────────────────────────────►
     │                                               │
     │  {requires2fa: true}                          │
     ◄──────────────────────────────────────────────┤
     │                                               │
     │  [User enters code from app]                 │
     │                                               │
     │  POST /auth/login/2fa                        │
     │  {email, password, code}                     │
     ├──────────────────────────────────────────────►
     │                                               │
     │  {access_token, refresh_token, user}         │
     ◄──────────────────────────────────────────────┤
```

---

## Frontend Integration

### React Example

```tsx
// components/TwoFactorSetup.tsx
import { useState } from 'react';
import { twoFactorApi } from '../lib/api';

export function TwoFactorSetup() {
  const [setup, setSetup] = useState<{
    qrCode: string;
    backupCodes: string[];
  } | null>(null);
  const [code, setCode] = useState('');

  const handleSetup = async () => {
    const data = await twoFactorApi.setup();
    setSetup(data);
  };

  const handleValidate = async () => {
    await twoFactorApi.validate(code);
    alert('2FA enabled successfully!');
  };

  if (!setup) {
    return (
      <button onClick={handleSetup}>
        Enable Two-Factor Authentication
      </button>
    );
  }

  return (
    <div>
      <h3>Scan this QR code with your authenticator app:</h3>
      <img src={setup.qrCode} alt="2FA QR Code" />

      <h3>Or enter this key manually:</h3>
      <code>{setup.manualEntryKey}</code>

      <h3>Save these backup codes:</h3>
      <ul>
        {setup.backupCodes.map((code, i) => (
          <li key={i}><code>{code}</code></li>
        ))}
      </ul>

      <h3>Enter code from app to confirm:</h3>
      <input
        type="text"
        value={code}
        onChange={(e) => setCode(e.target.value)}
        placeholder="123456"
        maxLength={6}
      />
      <button onClick={handleValidate}>Verify & Enable</button>
    </div>
  );
}
```

### Login with 2FA

```tsx
// components/Login.tsx
import { useState } from 'react';
import { authApi } from '../lib/api';

export function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [code, setCode] = useState('');
  const [requires2fa, setRequires2fa] = useState(false);

  const handleLogin = async () => {
    try {
      const response = await authApi.login(email, password);

      if (response.requires2fa) {
        setRequires2fa(true);
        return;
      }

      // Login successful
      handleSuccess(response);
    } catch (error) {
      console.error('Login failed:', error);
    }
  };

  const handleLogin2fa = async () => {
    try {
      const response = await authApi.login2fa(email, password, code);
      handleSuccess(response);
    } catch (error) {
      console.error('2FA verification failed:', error);
    }
  };

  if (requires2fa) {
    return (
      <div>
        <h3>Enter 2FA Code</h3>
        <input
          type="text"
          value={code}
          onChange={(e) => setCode(e.target.value)}
          placeholder="Enter code from authenticator"
          maxLength={6}
        />
        <button onClick={handleLogin2fa}>Verify</button>
      </div>
    );
  }

  return (
    <form onSubmit={(e) => { e.preventDefault(); handleLogin(); }}>
      <input
        type="email"
        value={email}
        onChange={(e) => setEmail(e.target.value)}
        placeholder="Email"
      />
      <input
        type="password"
        value={password}
        onChange={(e) => setPassword(e.target.value)}
        placeholder="Password"
      />
      <button type="submit">Login</button>
    </form>
  );
}
```

---

## Backup Codes

Backup codes allow login when the authenticator app is unavailable.

### Usage

- Enter a backup code instead of the TOTP code
- Each backup code can only be used **once**
- Regenerate codes periodically

### Storage

Backup codes are:
- Hashed in the database (like passwords)
- Never stored in plain text
- Cannot be retrieved, only regenerated

---

## Security Considerations

### TOTP Security

- Codes valid for 30 seconds (RFC 6238 standard)
- Small time window tolerance for clock drift
- Rate limiting recommended to prevent brute force

### Secret Storage

- Secrets encrypted in database
- Never exposed after initial setup
- User must re-setup if secret is lost

### Recommended Practices

1. **Require 2FA for sensitive actions** (password change, etc.)
2. **Rate limit 2FA verification** (prevent brute force)
3. **Log 2FA events** (for security audit)
4. **Remind users about backup codes**

---

## Configuration Options

| Option | Default | Description |
|--------|---------|-------------|
| `enabled` | true | Enable/disable 2FA globally |
| `issuer` | BetterAuth | Name in authenticator apps |
| `backup_codes_count` | 10 | Number of backup codes |

### Disable 2FA Globally

```yaml
better_auth:
    two_factor:
        enabled: false
```

2FA routes will return an error when disabled.

---

## Next Steps

- [OAuth Providers](06-OAUTH-PROVIDERS.md)
- [API Reference](09-API-REFERENCE.md)
- [Security](11-SECURITY.md)
