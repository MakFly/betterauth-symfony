# OAuth Providers

Configure social login with Google, GitHub, Microsoft, Facebook, Discord, and more.

## Supported Providers

| Provider | Status | Documentation |
|----------|--------|---------------|
| Google | ✅ Full support | [Console](https://console.cloud.google.com/apis/credentials) |
| GitHub | ✅ Full support | [Developer Settings](https://github.com/settings/developers) |
| Microsoft | ✅ Full support | [Azure Portal](https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps) |
| Facebook | ✅ Full support | [Meta Developers](https://developers.facebook.com/apps/) |
| Discord | ✅ Full support | [Discord Apps](https://discord.com/developers/applications) |
| Twitter/X | ✅ Full support | [Developer Portal](https://developer.twitter.com/en/portal) |
| Apple | ✅ Full support | [Apple Developer](https://developer.apple.com/account/resources/identifiers/list) |

---

## Quick Setup

### 1. Configuration

```yaml
# config/packages/better_auth.yaml
better_auth:
    oauth:
        providers:
            google:
                enabled: true
                client_id: '%env(GOOGLE_CLIENT_ID)%'
                client_secret: '%env(GOOGLE_CLIENT_SECRET)%'
                redirect_uri: '%env(APP_URL)%/auth/oauth/google/callback'

            github:
                enabled: true
                client_id: '%env(GITHUB_CLIENT_ID)%'
                client_secret: '%env(GITHUB_CLIENT_SECRET)%'
                redirect_uri: '%env(APP_URL)%/auth/oauth/github/callback'
```

### 2. Environment Variables

```env
# .env
APP_URL=https://myapp.com

GOOGLE_CLIENT_ID=xxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=xxx

GITHUB_CLIENT_ID=xxx
GITHUB_CLIENT_SECRET=xxx
```

### 3. Use in Frontend

```javascript
// Redirect to OAuth provider
async function loginWithGoogle() {
  const response = await fetch('/auth/oauth/google');
  const { url } = await response.json();
  window.location.href = url;
}
```

---

## Provider Setup Guides

### Google OAuth

1. Go to [Google Cloud Console](https://console.cloud.google.com/apis/credentials)
2. Create a project or select existing
3. Enable "Google+ API" and "People API"
4. Go to "Credentials" → "Create Credentials" → "OAuth client ID"
5. Application type: "Web application"
6. Add authorized redirect URI: `https://yourapp.com/auth/oauth/google/callback`
7. Copy Client ID and Client Secret

```env
GOOGLE_CLIENT_ID=123456789-abc.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxx
```

### GitHub OAuth

1. Go to [GitHub Developer Settings](https://github.com/settings/developers)
2. "New OAuth App"
3. Fill in:
   - Application name: Your App Name
   - Homepage URL: `https://yourapp.com`
   - Authorization callback URL: `https://yourapp.com/auth/oauth/github/callback`
4. Copy Client ID and generate Client Secret

```env
GITHUB_CLIENT_ID=Iv1.xxxxxxxxxxxx
GITHUB_CLIENT_SECRET=xxxxxxxxxxxxxxxx
```

### Microsoft OAuth

1. Go to [Azure Portal](https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps)
2. "New registration"
3. Fill in:
   - Name: Your App Name
   - Supported account types: "Accounts in any organizational directory and personal Microsoft accounts"
   - Redirect URI: Web → `https://yourapp.com/auth/oauth/microsoft/callback`
4. Copy Application (client) ID
5. Go to "Certificates & secrets" → "New client secret"

```env
MICROSOFT_CLIENT_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
MICROSOFT_CLIENT_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxx
```

### Facebook OAuth

1. Go to [Meta for Developers](https://developers.facebook.com/apps/)
2. "Create App" → "Consumer"
3. Add "Facebook Login" product
4. Settings → Basic: Copy App ID and App Secret
5. Facebook Login → Settings: Add redirect URI

```env
FACEBOOK_CLIENT_ID=1234567890
FACEBOOK_CLIENT_SECRET=xxxxxxxxxxxxxxxx
```

### Discord OAuth

1. Go to [Discord Developer Portal](https://discord.com/developers/applications)
2. "New Application"
3. Go to "OAuth2"
4. Add redirect: `https://yourapp.com/auth/oauth/discord/callback`
5. Copy Client ID and Client Secret

```env
DISCORD_CLIENT_ID=1234567890
DISCORD_CLIENT_SECRET=xxxxxxxxxxxxxxxx
```

---

## OAuth Flow

### 1. Get Authorization URL

```bash
curl -X GET http://localhost:8000/auth/oauth/google
```

**Response:**
```json
{
  "url": "https://accounts.google.com/o/oauth2/v2/auth?client_id=xxx&redirect_uri=xxx&scope=email%20profile&response_type=code&state=xxx",
  "state": "abc123xyz"
}
```

### 2. Redirect User

Redirect user to the `url` from the response.

### 3. Handle Callback

After user authorizes, provider redirects to:
```
https://yourapp.com/auth/oauth/google/callback?code=xxx&state=abc123xyz
```

BetterAuth handles this automatically and returns:
```json
{
  "access_token": "v4.local.eyJ...",
  "refresh_token": "rt_abc123...",
  "user": {
    "id": "019ab13e-40f1-7b21-a672-f403d5277ec7",
    "email": "user@gmail.com",
    "name": "John Doe",
    "emailVerified": true
  }
}
```

---

## Frontend Integration

### React Example

```typescript
// components/SocialLogin.tsx
import { useState } from 'react';

const providers = [
  { id: 'google', name: 'Google', icon: '🔷' },
  { id: 'github', name: 'GitHub', icon: '🐙' },
  { id: 'microsoft', name: 'Microsoft', icon: '🪟' },
];

export function SocialLogin() {
  const [loading, setLoading] = useState<string | null>(null);

  const handleLogin = async (provider: string) => {
    setLoading(provider);
    try {
      const response = await fetch(`/auth/oauth/${provider}`);
      const { url } = await response.json();
      window.location.href = url;
    } catch (error) {
      console.error('OAuth error:', error);
      setLoading(null);
    }
  };

  return (
    <div className="social-login">
      {providers.map(({ id, name, icon }) => (
        <button
          key={id}
          onClick={() => handleLogin(id)}
          disabled={loading !== null}
        >
          {loading === id ? 'Redirecting...' : `${icon} Sign in with ${name}`}
        </button>
      ))}
    </div>
  );
}
```

### Callback Handler

```typescript
// pages/OAuthCallback.tsx
import { useEffect } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';

export function OAuthCallback() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();

  useEffect(() => {
    const code = searchParams.get('code');
    const state = searchParams.get('state');
    const provider = window.location.pathname.split('/').pop();

    if (code) {
      // The backend handles the callback automatically
      // Tokens are returned in the response
      handleCallback(provider, code, state);
    }
  }, [searchParams]);

  const handleCallback = async (provider: string, code: string, state: string | null) => {
    try {
      const response = await fetch(
        `/auth/oauth/${provider}/callback?code=${code}&state=${state}`
      );
      const data = await response.json();

      if (data.access_token) {
        localStorage.setItem('access_token', data.access_token);
        localStorage.setItem('refresh_token', data.refresh_token);
        navigate('/dashboard');
      }
    } catch (error) {
      console.error('Callback error:', error);
      navigate('/login?error=oauth_failed');
    }
  };

  return <div>Processing authentication...</div>;
}
```

---

## Account Linking

When a user logs in with OAuth:

1. **New user**: Account is created automatically
2. **Existing user (same email)**: Accounts are linked
3. **Existing user (different email)**: New account created

### Manual Linking

To allow users to link additional OAuth providers:

```php
// Controller
#[Route('/auth/link/{provider}', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
public function linkProvider(string $provider): JsonResponse
{
    $user = $this->getUser();

    // Get OAuth URL with linking flag
    $result = $this->oauthManager->getAuthorizationUrl($provider, [
        'link_to_user' => $user->getId(),
    ]);

    return $this->json([
        'url' => $result['url'],
        'state' => $result['state'],
    ]);
}
```

---

## 2FA with OAuth

When a user with 2FA enabled logs in via OAuth:

```json
{
  "requires2fa": true,
  "message": "Two-factor authentication required",
  "user": {
    "id": "xxx",
    "email": "user@gmail.com"
  }
}
```

The frontend should then show the 2FA form and call `/auth/login/2fa`.

---

## Security Considerations

### State Parameter

BetterAuth uses CSRF protection via the `state` parameter:
- Generated randomly on authorization request
- Validated on callback
- Prevents CSRF attacks

### Redirect URI Validation

- Only configured redirect URIs are accepted
- Must match exactly (including trailing slashes)
- Use HTTPS in production

### Token Storage

OAuth tokens from providers are NOT stored. Only:
- User email
- User name
- Provider ID (for account linking)

---

## Troubleshooting

### "Invalid redirect URI"

Ensure the redirect URI in your config matches exactly what's configured in the provider's dashboard.

### "State mismatch"

The state parameter didn't match. This can happen if:
- User bookmarked the OAuth callback URL
- Session expired during OAuth flow
- CSRF attack attempt

### "Email already exists"

User already has an account with that email. Options:
1. Link the OAuth provider to existing account
2. Ask user to login with password first

---

## Next Steps

- [Two-Factor Authentication](07-TWO-FACTOR-AUTH.md)
- [API Reference](09-API-REFERENCE.md)
- [Security](11-SECURITY.md)
