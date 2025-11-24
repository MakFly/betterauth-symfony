<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Service;

use BetterAuth\Core\Interfaces\EmailSenderInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Symfony Mailer implementation of EmailSenderInterface.
 *
 * This service is final to ensure consistent email sending behavior.
 */
final class SymfonyMailerEmailSender implements EmailSenderInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $fromEmail = 'noreply@example.com',
        private readonly string $fromName = 'BetterAuth'
    ) {
    }

    public function sendMagicLink(string $to, string $magicLink): bool
    {
        try {
            $email = (new Email())
                ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
                ->to($to)
                ->subject('Your Magic Link')
                ->html(sprintf(
                    '<p>Click the link below to sign in:</p><p><a href="%s">Sign In</a></p><p>This link expires in 10 minutes.</p>',
                    $magicLink
                ));

            $this->mailer->send($email);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function sendVerificationEmail(string $to, string $verificationLink): bool
    {
        try {
            $email = (new Email())
                ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
                ->to($to)
                ->subject('Verify Your Email')
                ->html(sprintf(
                    '<p>Click the link below to verify your email address:</p><p><a href="%s">Verify Email</a></p><p>This link expires in 24 hours.</p>',
                    $verificationLink
                ));

            $this->mailer->send($email);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function sendPasswordReset(string $to, string $resetLink): bool
    {
        try {
            $email = (new Email())
                ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
                ->to($to)
                ->subject('Reset Your Password')
                ->html(sprintf(
                    '<p>Click the link below to reset your password:</p><p><a href="%s">Reset Password</a></p><p>This link expires in 1 hour.</p>',
                    $resetLink
                ));

            $this->mailer->send($email);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function sendTwoFactorCode(string $to, string $code): bool
    {
        try {
            $email = (new Email())
                ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
                ->to($to)
                ->subject('Your 2FA Code')
                ->html(sprintf(
                    '<p>Your two-factor authentication code is:</p><p><strong>%s</strong></p><p>This code expires in 5 minutes.</p>',
                    $code
                ));

            $this->mailer->send($email);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
