# Passkeys & WebAuthn

Passwordless authentication using FIDO2/WebAuthn standards.

## Overview

Passkeys provide:
- **Passwordless authentication** - No passwords to remember
- **Phishing resistant** - Bound to specific domains
- **Biometric support** - Fingerprint, Face ID, Windows Hello
- **Cross-device** - Sync across devices (iCloud, Google, etc.)

## Configuration

```yaml
# config/packages/better_auth.yaml
better_auth:
    passkeys:
        enabled: true
        rp_name: 'My Application'
        rp_id: 'myapp.com'
        # Optional: attestation preference
        attestation: 'none'  # none, indirect, direct
        # Optional: user verification
        user_verification: 'preferred'  # required, preferred, discouraged
```

---

## Registration Flow

### 1. Get Registration Options

```bash
curl -X POST http://localhost:8000/auth/passkey/register/options \
  -H "Authorization: Bearer {access_token}"
```

**Response:**
```json
{
  "challenge": "base64-encoded-challenge",
  "rp": {
    "name": "My Application",
    "id": "myapp.com"
  },
  "user": {
    "id": "base64-user-id",
    "name": "user@example.com",
    "displayName": "John Doe"
  },
  "pubKeyCredParams": [
    { "type": "public-key", "alg": -7 },
    { "type": "public-key", "alg": -257 }
  ],
  "authenticatorSelection": {
    "authenticatorAttachment": "platform",
    "userVerification": "preferred",
    "residentKey": "preferred"
  },
  "timeout": 60000
}
```

### 2. Create Credential (Frontend)

```typescript
const options = await fetch('/auth/passkey/register/options', {
  method: 'POST',
  headers: { 'Authorization': `Bearer ${token}` },
}).then(r => r.json());

// Convert base64 to ArrayBuffer
options.challenge = base64ToArrayBuffer(options.challenge);
options.user.id = base64ToArrayBuffer(options.user.id);

// Create credential using WebAuthn API
const credential = await navigator.credentials.create({
  publicKey: options,
});
```

### 3. Verify Registration

```bash
curl -X POST http://localhost:8000/auth/passkey/register/verify \
  -H "Authorization: Bearer {access_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "id": "credential-id",
    "rawId": "base64-raw-id",
    "response": {
      "clientDataJSON": "base64-client-data",
      "attestationObject": "base64-attestation"
    },
    "type": "public-key"
  }'
```

---

## Authentication Flow

### 1. Get Authentication Options

```bash
curl -X POST http://localhost:8000/auth/passkey/login/options \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com"}'
```

**Response:**
```json
{
  "challenge": "base64-challenge",
  "timeout": 60000,
  "rpId": "myapp.com",
  "allowCredentials": [
    {
      "type": "public-key",
      "id": "base64-credential-id",
      "transports": ["internal", "hybrid"]
    }
  ],
  "userVerification": "preferred"
}
```

### 2. Get Credential (Frontend)

```typescript
const options = await fetch('/auth/passkey/login/options', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ email }),
}).then(r => r.json());

// Convert base64 to ArrayBuffer
options.challenge = base64ToArrayBuffer(options.challenge);
options.allowCredentials = options.allowCredentials.map(cred => ({
  ...cred,
  id: base64ToArrayBuffer(cred.id),
}));

// Get credential using WebAuthn API
const assertion = await navigator.credentials.get({
  publicKey: options,
});
```

### 3. Verify Authentication

```bash
curl -X POST http://localhost:8000/auth/passkey/login/verify \
  -H "Content-Type: application/json" \
  -d '{
    "id": "credential-id",
    "rawId": "base64-raw-id",
    "response": {
      "clientDataJSON": "base64-client-data",
      "authenticatorData": "base64-auth-data",
      "signature": "base64-signature"
    },
    "type": "public-key"
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

---

## Frontend Implementation

### React Component

```tsx
// components/PasskeyLogin.tsx
import { useState } from 'react';

export function PasskeyLogin() {
  const [email, setEmail] = useState('');
  const [loading, setLoading] = useState(false);

  const handleLogin = async () => {
    if (!window.PublicKeyCredential) {
      alert('Passkeys not supported');
      return;
    }

    setLoading(true);

    try {
      // 1. Get options from server
      const optionsRes = await fetch('/auth/passkey/login/options', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email }),
      });
      const options = await optionsRes.json();

      // 2. Convert base64 to ArrayBuffer
      options.challenge = base64ToArrayBuffer(options.challenge);
      options.allowCredentials = options.allowCredentials?.map((cred: any) => ({
        ...cred,
        id: base64ToArrayBuffer(cred.id),
      }));

      // 3. Get credential from authenticator
      const credential = await navigator.credentials.get({
        publicKey: options,
      }) as PublicKeyCredential;

      // 4. Send to server for verification
      const response = credential.response as AuthenticatorAssertionResponse;
      const verifyRes = await fetch('/auth/passkey/login/verify', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          id: credential.id,
          rawId: arrayBufferToBase64(credential.rawId),
          response: {
            clientDataJSON: arrayBufferToBase64(response.clientDataJSON),
            authenticatorData: arrayBufferToBase64(response.authenticatorData),
            signature: arrayBufferToBase64(response.signature),
          },
          type: credential.type,
        }),
      });

      const data = await verifyRes.json();
      // Handle successful login
      console.log('Logged in:', data);
    } catch (error) {
      console.error('Passkey login failed:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div>
      <input
        type="email"
        value={email}
        onChange={(e) => setEmail(e.target.value)}
        placeholder="Email"
      />
      <button onClick={handleLogin} disabled={loading}>
        {loading ? 'Authenticating...' : 'Sign in with Passkey'}
      </button>
    </div>
  );
}

// Helper functions
function base64ToArrayBuffer(base64: string): ArrayBuffer {
  const binary = atob(base64.replace(/-/g, '+').replace(/_/g, '/'));
  const bytes = new Uint8Array(binary.length);
  for (let i = 0; i < binary.length; i++) {
    bytes[i] = binary.charCodeAt(i);
  }
  return bytes.buffer;
}

function arrayBufferToBase64(buffer: ArrayBuffer): string {
  const bytes = new Uint8Array(buffer);
  let binary = '';
  for (let i = 0; i < bytes.byteLength; i++) {
    binary += String.fromCharCode(bytes[i]);
  }
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}
```

### Check Passkey Support

```typescript
async function isPasskeySupported(): Promise<boolean> {
  if (!window.PublicKeyCredential) {
    return false;
  }

  // Check for platform authenticator
  if (PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable) {
    return await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
  }

  return true;
}
```

---

## Managing Passkeys

### List User's Passkeys

```bash
curl -X GET http://localhost:8000/auth/passkey/list \
  -H "Authorization: Bearer {access_token}"
```

**Response:**
```json
{
  "passkeys": [
    {
      "id": "credential-id",
      "name": "MacBook Pro",
      "createdAt": "2024-01-15T10:00:00Z",
      "lastUsedAt": "2024-01-20T14:30:00Z"
    }
  ]
}
```

### Delete Passkey

```bash
curl -X DELETE http://localhost:8000/auth/passkey/{credentialId} \
  -H "Authorization: Bearer {access_token}"
```

---

## Security Considerations

### Domain Binding

Passkeys are bound to your domain (RP ID). Ensure:
- Use your actual domain in production
- `rp_id` must match your domain or a valid parent domain

### User Verification

| Setting | Description |
|---------|-------------|
| `required` | Always require biometric/PIN |
| `preferred` | Request but don't require |
| `discouraged` | Skip if possible |

### Attestation

| Setting | Description |
|---------|-------------|
| `none` | No attestation (recommended for most apps) |
| `indirect` | Allow anonymized attestation |
| `direct` | Require full attestation |

---

## Browser Support

| Browser | Support |
|---------|---------|
| Chrome | Full |
| Safari | Full |
| Firefox | Full |
| Edge | Full |
| Mobile Safari | Full (iOS 16+) |
| Chrome Android | Full |

---

## Troubleshooting

### "NotAllowedError"

User cancelled or authenticator not available.

### "SecurityError"

Domain mismatch - check `rp_id` configuration.

### "InvalidStateError"

Credential already registered or operation already in progress.

---

## Next Steps

- [Two-Factor Authentication](07-TWO-FACTOR-AUTH.md)
- [OAuth Providers](06-OAUTH-PROVIDERS.md)
- [Security](11-SECURITY.md)
