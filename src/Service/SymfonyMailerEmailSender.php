<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Service;

use BetterAuth\Core\Interfaces\EmailSenderInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Symfony Mailer implementation of EmailSenderInterface with Twig template support.
 *
 * Templates can be overridden by creating them in your project:
 * - templates/emails/betterauth/magic_link.html.twig
 * - templates/emails/betterauth/email_verification.html.twig
 * - templates/emails/betterauth/password_reset.html.twig
 * - templates/emails/betterauth/two_factor_code.html.twig
 */
final class SymfonyMailerEmailSender implements EmailSenderInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly string $fromEmail = 'noreply@example.com',
        private readonly string $fromName = 'BetterAuth'
    ) {
    }

    public function sendMagicLink(string $to, string $magicLink): bool
    {
        try {
            $html = $this->renderTemplate('magic_link.html.twig', [
                'magicLink' => $magicLink,
            ]);

            $email = (new Email())
                ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
                ->to($to)
                ->subject('Your Magic Link')
                ->html($html);

            $this->mailer->send($email);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function sendVerificationEmail(string $to, string $verificationLink): bool
    {
        try {
            $html = $this->renderTemplate('email_verification.html.twig', [
                'verificationLink' => $verificationLink,
            ]);

            $email = (new Email())
                ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
                ->to($to)
                ->subject('Verify Your Email')
                ->html($html);

            $this->mailer->send($email);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function sendPasswordReset(string $to, string $resetLink): bool
    {
        try {
            $html = $this->renderTemplate('password_reset.html.twig', [
                'resetLink' => $resetLink,
            ]);

            $email = (new Email())
                ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
                ->to($to)
                ->subject('Reset Your Password')
                ->html($html);

            $this->mailer->send($email);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function sendTwoFactorCode(string $to, string $code): bool
    {
        try {
            $html = $this->renderTemplate('two_factor_code.html.twig', [
                'code' => $code,
            ]);

            $email = (new Email())
                ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
                ->to($to)
                ->subject('Your 2FA Code')
                ->html($html);

            $this->mailer->send($email);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Render email template with fallback to default templates.
     *
     * Looks for templates in this order:
     * 1. templates/emails/betterauth/{template}  (user override)
     * 2. @BetterAuth/emails/{template}           (default template)
     */
    private function renderTemplate(string $template, array $context = []): string
    {
        $userTemplate = sprintf('emails/betterauth/%s', $template);
        $defaultTemplate = sprintf('@BetterAuth/emails/%s', $template);

        // Try user override first
        if ($this->twig->getLoader()->exists($userTemplate)) {
            return $this->twig->render($userTemplate, $context);
        }

        // Fallback to default template
        return $this->twig->render($defaultTemplate, $context);
    }
}
