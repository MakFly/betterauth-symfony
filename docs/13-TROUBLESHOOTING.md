# Troubleshooting

Common issues and their solutions.

## Installation Issues

### "Command not found"

```bash
# Clear cache
php bin/console cache:clear

# Check bundle is registered
php bin/console debug:container BetterAuth

# If still not found, check config/bundles.php
```

**Solution:** Ensure the bundle is in `config/bundles.php`:
```php
return [
    // ...
    BetterAuth\Symfony\BetterAuthBundle::class => ['all' => true],
];
```

### "Class not found" errors

```bash
# Dump autoloader
composer dump-autoload

# Clear Symfony cache
php bin/console cache:clear

# Reinstall dependencies
rm -rf vendor/
composer install
```

### Migration errors

```bash
# Check schema
php bin/console doctrine:schema:validate

# Force migration
php bin/console doctrine:migrations:migrate --no-interaction

# Create migration manually
php bin/console doctrine:migrations:diff
```

---

## Authentication Issues

### "No token provided"

**Cause:** Missing `Authorization` header.

**Solution:**
```typescript
// Add header to requests
headers: {
  'Authorization': `Bearer ${accessToken}`
}
```

### "Invalid token"

**Causes:**
- Token corrupted
- Wrong secret key
- Token format invalid

**Solutions:**
```bash
# Check secret matches
php bin/console debug:config better_auth secret

# Regenerate token
curl -X POST /auth/refresh -d '{"refreshToken": "xxx"}'
```

### "Token expired"

**Cause:** Access token lifetime exceeded.

**Solution:** Implement token refresh:
```typescript
// Refresh token before expiration
const response = await fetch('/auth/refresh', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ refreshToken }),
});
```

### "Invalid credentials"

**Causes:**
- Wrong email/password
- User doesn't exist
- Password hash mismatch

**Debug:**
```php
// Check user exists
$user = $userRepository->findByEmail($email);
dump($user);

// Check password
$isValid = $passwordHasher->isPasswordValid($user, $password);
dump($isValid);
```

---

## Session Issues

### Cookies not being set

**Causes:**
- CORS misconfiguration
- SameSite policy
- Missing `withCredentials`

**Solutions:**

1. Check CORS:
```yaml
# config/packages/nelmio_cors.yaml
nelmio_cors:
    defaults:
        allow_credentials: true
```

2. Check frontend:
```typescript
axios.defaults.withCredentials = true;
```

3. Check cookie settings:
```php
setCookie('token', $value, [
    'samesite' => 'lax',  // or 'none' for cross-site
    'secure' => true,     // HTTPS only in production
]);
```

### Session not persisting

**Causes:**
- Session lifetime too short
- Cookie being rejected

**Solutions:**
```yaml
better_auth:
    session:
        lifetime: 604800  # 7 days
```

---

## OAuth Issues

### "Invalid redirect URI"

**Cause:** Mismatch between configured URI and provider settings.

**Solution:** Ensure exact match:
```yaml
# config/packages/better_auth.yaml
better_auth:
    oauth:
        providers:
            google:
                redirect_uri: 'https://myapp.com/auth/oauth/google/callback'
```

Must match exactly in Google Console, including:
- Protocol (http vs https)
- Domain
- Path
- Trailing slash

### "State mismatch"

**Causes:**
- Session expired during OAuth flow
- User bookmarked callback URL
- Multiple tabs/windows

**Solution:** Start OAuth flow again.

### OAuth callback not working

**Debug:**
```bash
# Check route exists
php bin/console debug:router | grep oauth

# Check logs
tail -f var/log/dev.log
```

---

## 2FA Issues

### "Invalid 2FA code"

**Causes:**
- Clock drift (server vs phone)
- Wrong code entered
- Using old code

**Solutions:**
- Check server time: `date`
- Sync phone time to network
- Wait for new code (30 seconds)

### QR code not scanning

**Causes:**
- Image too small
- Invalid format

**Debug:**
```php
// Check QR code data
dump($totpData->getUri());
// Should be: otpauth://totp/Issuer:email?secret=XXX&issuer=Issuer
```

### Lost authenticator app

**Solution:** Use backup codes:
```bash
# Enter backup code instead of TOTP code
curl -X POST /auth/login/2fa -d '{"email":"...", "password":"...", "code":"12345678"}'
```

---

## Performance Issues

### Slow authentication

**Causes:**
- Database queries
- Password hashing
- Token generation

**Solutions:**
```yaml
# Use database indexes
# In your User entity
#[ORM\Index(columns: ['email'])]
```

### High memory usage

**Cause:** Argon2id memory cost too high.

**Solution:**
```yaml
# config/packages/security.yaml
security:
    password_hashers:
        App\Entity\User:
            algorithm: argon2id
            memory_cost: 32768  # Reduce from default 65536
```

---

## Database Issues

### "Table not found"

```bash
# Run migrations
php bin/console doctrine:migrations:migrate

# Or create schema directly
php bin/console doctrine:schema:update --force
```

### "Column not found"

**Cause:** Entity changed but migration not run.

```bash
# Generate migration
php bin/console doctrine:migrations:diff

# Run it
php bin/console doctrine:migrations:migrate
```

### Foreign key errors

**Cause:** Cascade delete not configured.

**Solution:**
```php
#[ORM\OneToMany(targetEntity: Session::class, mappedBy: 'user', cascade: ['remove'])]
private Collection $sessions;
```

---

## Configuration Issues

### Config not loading

```bash
# Check config syntax
php bin/console lint:yaml config/packages/better_auth.yaml

# Debug config
php bin/console debug:config better_auth
```

### Environment variables not working

```bash
# Check .env is loaded
php bin/console debug:dotenv

# Check specific variable
php bin/console debug:container --env-var=BETTER_AUTH_SECRET
```

---

## Debugging

### Enable debug mode

```yaml
# config/packages/dev/better_auth.yaml
better_auth:
    debug: true
```

### Check logs

```bash
# Real-time logs
tail -f var/log/dev.log

# Filter auth logs
grep better_auth var/log/dev.log

# Specific errors
grep ERROR var/log/dev.log
```

### Debug requests

```php
// In controller
dump($request->headers->all());
dump($request->getContent());
dump($request->cookies->all());
```

### Debug tokens

```bash
# Decode Paseto token (for debugging only)
# Use a Paseto library or online decoder
```

---

## Common Errors Reference

| Error | Code | Solution |
|-------|------|----------|
| No token provided | 401 | Add Authorization header |
| Invalid token | 401 | Token corrupted, refresh |
| Token expired | 401 | Call /auth/refresh |
| Invalid credentials | 401 | Check email/password |
| User already exists | 400 | Email taken, use different |
| Invalid email format | 400 | Use valid email |
| Password too short | 400 | Min 8 characters |
| 2FA code invalid | 401 | Check code, wait for new |
| Rate limit exceeded | 429 | Wait and retry |
| State mismatch | 400 | Restart OAuth flow |

---

## Getting Help

1. **Check logs**: `var/log/dev.log`
2. **Check config**: `php bin/console debug:config better_auth`
3. **GitHub Issues**: [github.com/MakFly/betterauth-symfony/issues](https://github.com/MakFly/betterauth-symfony/issues)

---

## Next Steps

- [Security](11-SECURITY.md)
- [Testing](12-TESTING.md)
- [API Reference](09-API-REFERENCE.md)
