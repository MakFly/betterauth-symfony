# Email Templates

Customize email templates for Magic Link, verification, password reset, and 2FA.

## Default Templates

BetterAuth includes 4 default email templates:
- **Magic Link** - Passwordless authentication
- **Email Verification** - New account verification
- **Password Reset** - Password recovery
- **Two-Factor Code** - 2FA authentication codes

## Customization

### 1. Create Template Override

Create your custom templates in your Symfony project:

```
templates/
└── emails/
    └── betterauth/
        ├── magic_link.html.twig
        ├── email_verification.html.twig
        ├── password_reset.html.twig
        └── two_factor_code.html.twig
```

### 2. Copy Default Template

Start by copying the default template:

```bash
cp vendor/betterauth/symfony-bundle/src/Resources/views/emails/magic_link.html.twig \
   templates/emails/betterauth/magic_link.html.twig
```

### 3. Customize

```twig
{# templates/emails/betterauth/magic_link.html.twig #}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sign In to {{ app_name }}</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <img src="{{ asset('images/logo.png') }}" alt="Logo" style="max-width: 200px;">

        <h1>Your Magic Link</h1>
        <p>Click the button below to sign in:</p>

        <a href="{{ magicLink }}" style="display: inline-block; padding: 12px 24px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px;">
            Sign In
        </a>

        <p style="color: #999; font-size: 12px;">
            This link expires in 10 minutes.
        </p>
    </div>
</body>
</html>
```

---

## Available Variables

### Magic Link Template
- `magicLink` - The full URL for passwordless login

### Email Verification Template
- `verificationLink` - The full URL to verify the email address

### Password Reset Template
- `resetLink` - The full URL to reset the password

### Two-Factor Code Template
- `code` - The 6-digit 2FA code

---

## Template Override Priority

BetterAuth looks for templates in this order:

1. **Your Project** - `templates/emails/betterauth/{template}` (highest priority)
2. **Default** - `@BetterAuth/emails/{template}` (fallback)

---

## Best Practices

### Responsive Design

Use inline CSS and table-based layouts for maximum email client compatibility:

```twig
<table role="presentation" style="width: 100%; border-collapse: collapse;">
    <tr>
        <td style="padding: 20px;">
            {# Your content #}
        </td>
    </tr>
</table>
```

### Test Across Email Clients

Test your templates in:
- Gmail
- Outlook
- Apple Mail
- Mobile devices

### Keep It Simple

- Use inline styles (no external CSS)
- Avoid JavaScript
- Use web-safe fonts
- Keep images to a minimum

---

## Development Tips

### Testing Locally

Use MailHog or MailCatcher:

```env
MAILER_DSN=smtp://localhost:1025
```

Then view emails at `http://localhost:8025`

### Preview in Browser

```php
// src/Controller/DevController.php
#[Route('/dev/email-preview/{template}')]
public function previewEmail(string $template, Environment $twig): Response
{
    $html = $twig->render("emails/betterauth/{$template}.html.twig", [
        'magicLink' => 'https://example.com/auth/magic-link?token=xxx',
        'verificationLink' => 'https://example.com/verify?token=xxx',
        'resetLink' => 'https://example.com/reset?token=xxx',
        'code' => '123456',
    ]);

    return new Response($html);
}
```

---

## Examples

### Adding Logo

```twig
<img src="{{ absolute_url(asset('images/logo.png')) }}"
     alt="Company Logo"
     style="max-width: 200px; margin-bottom: 20px;">
```

### Custom Button Styles

```twig
<a href="{{ magicLink }}"
   style="display: inline-block;
          padding: 15px 30px;
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          color: #ffffff;
          text-decoration: none;
          border-radius: 8px;
          font-weight: bold;">
    Sign In to Your Account
</a>
```

### Adding Footer

```twig
<div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; color: #999; font-size: 12px;">
    <p>&copy; 2025 Your Company. All rights reserved.</p>
    <p>
        <a href="https://yourcompany.com" style="color: #999;">Website</a> |
        <a href="https://yourcompany.com/privacy" style="color: #999;">Privacy</a>
    </p>
</div>
```

---

## Troubleshooting

### Templates Not Loading

1. Clear cache: `php bin/console cache:clear`
2. Check template path: `templates/emails/betterauth/`
3. Check file permissions

### Styling Issues

1. Use inline styles
2. Test in multiple clients
3. Use tables for layout

### Variables Not Available

Use correct variable names (case-sensitive):
- `{{ magicLink }}`
- `{{ verificationLink }}`
- `{{ resetLink }}`
- `{{ code }}`

---

## Next Steps

- [Entity Customization](16-ENTITY-CUSTOMIZATION.md)
- [Configuration](02-CONFIGURATION.md)
- [API Reference](09-API-REFERENCE.md)
