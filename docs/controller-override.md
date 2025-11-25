# Controller Override Guide

This guide explains how to customize BetterAuth controllers in your Symfony application.

## Overview

BetterAuth provides two mechanisms for customizing controllers:

1. **Configuration-Driven Override** - Replace entire controllers via `better_auth.yaml`
2. **Inheritance-Based Extension** - Extend abstract controllers and override hook methods

## 1. Override via Configuration

Replace a bundle controller with your own class:

```yaml
# config/packages/better_auth.yaml
better_auth:
    controllers:
        groups:
            credentials:
                enabled: true
                class: App\Controller\Auth\CustomCredentialsController
```

## 2. Extension via Inheritance

Extend an abstract controller and override specific hooks:

```php
<?php

namespace App\Controller\Auth;

use BetterAuth\Symfony\Controller\Abstract\AbstractCredentialsController;
use BetterAuth\Core\Entities\User;
use Symfony\Component\HttpFoundation\Request;

class CustomCredentialsController extends AbstractCredentialsController
{
    // Called before registration
    protected function beforeRegister(Request $request, array $data): void
    {
        // Add invite code validation
        if (!$this->validateInviteCode($data['invite_code'] ?? null)) {
            throw new \InvalidArgumentException('Invalid invite code');
        }
    }

    // Called after successful registration
    protected function afterRegister(User $user, array $result): void
    {
        // Send welcome email
        $this->welcomeEmailService->send($user);

        // Track analytics
        $this->analytics->track('user_registered', ['user_id' => $user->getId()]);
    }

    // Transform data before creating user
    protected function transformRegistrationData(array $data): array
    {
        return [
            'name' => $data['name'] ?? null,
            'referral_code' => $data['referral_code'] ?? null,
        ];
    }
}
```

## Available Hooks by Controller

### CredentialsController

| Hook | Description |
|------|-------------|
| `beforeRegister()` | Called before registration |
| `afterRegister()` | Called after successful registration |
| `transformRegistrationData()` | Modify data before creating user |
| `createRegistrationResponse()` | Customize response format |
| `beforeLogin()` | Called before login attempt |
| `afterLogin()` | Called after successful login |
| `create2faRequiredResponse()` | Customize 2FA challenge response |
| `createLoginResponse()` | Customize login response format |

### TokenController

| Hook | Description |
|------|-------------|
| `beforeMe()` | Called before returning user info |
| `createMeResponse()` | Customize me response format |
| `beforeRefresh()` | Called before token refresh |
| `afterRefresh()` | Called after successful refresh |
| `beforeLogout()` | Called before logout |
| `afterLogout()` | Called after logout |

### SessionController

| Hook | Description |
|------|-------------|
| `beforeListSessions()` | Called before listing sessions |
| `formatSession()` | Customize session format |
| `beforeRevokeSession()` | Called before revoking |
| `afterRevokeSession()` | Called after revoking |

### OAuthController

| Hook | Description |
|------|-------------|
| `filterAvailableProviders()` | Filter providers list |
| `afterOAuthCallback()` | Called after OAuth success |
| `getSuccessRedirectUrl()` | Customize success redirect |
| `get2faRedirectUrl()` | Customize 2FA redirect |
| `getErrorRedirectUrl()` | Customize error redirect |

### TwoFactorController

| Hook | Description |
|------|-------------|
| `beforeSetup()` | Called before 2FA setup |
| `createSetupResponse()` | Customize setup response |
| `afterValidate()` | Called after 2FA enabled |
| `afterDisable()` | Called after 2FA disabled |

### PasswordResetController

| Hook | Description |
|------|-------------|
| `beforeForgotPassword()` | Called before sending reset email |
| `getResetCallbackUrl()` | Customize reset callback URL |
| `afterResetPassword()` | Called after password reset |

### EmailVerificationController

| Hook | Description |
|------|-------------|
| `beforeSendVerification()` | Called before sending email |
| `getVerificationCallbackUrl()` | Customize callback URL |
| `afterVerify()` | Called after email verified |

### MagicLinkController

| Hook | Description |
|------|-------------|
| `beforeSendMagicLink()` | Called before sending link |
| `getMagicLinkCallbackUrl()` | Customize callback URL |
| `afterVerifyMagicLink()` | Called after link verified |

### GuestSessionController

| Hook | Description |
|------|-------------|
| `beforeCreateGuestSession()` | Called before creating session |
| `afterConvertToUser()` | Called after converting to user |
| `afterDeleteGuestSession()` | Called after deleting session |

## Injecting Dependencies

When extending abstract controllers, inject your dependencies in the constructor:

```php
public function __construct(
    \BetterAuth\Core\AuthManager $authManager,
    \BetterAuth\Providers\TotpProvider\TotpProvider $totpProvider,
    private readonly WelcomeEmailService $welcomeEmail,
    private readonly AnalyticsService $analytics,
) {
    parent::__construct($authManager, $totpProvider);
}
```

## Registering Custom Controller

After creating your custom controller, register it in `better_auth.yaml`:

```yaml
better_auth:
    controllers:
        groups:
            credentials:
                class: App\Controller\Auth\CustomCredentialsController
```

The bundle will automatically use your controller instead of the default one.
