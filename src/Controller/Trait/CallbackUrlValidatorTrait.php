<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Controller\Trait;

/**
 * Validates user-supplied callback URLs against the trusted frontend origin.
 *
 * Magic-link and email-verification flows embed a caller-provided callbackUrl in
 * the link sent by email. Without validation this is an open-redirect / token
 * exfiltration vector (the one-time token can be delivered to an attacker host).
 */
trait CallbackUrlValidatorTrait
{
    private function isAllowedCallbackUrl(string $callbackUrl, string $frontendUrl): bool
    {
        $cb = parse_url($callbackUrl);
        $fe = parse_url($frontendUrl);

        if (!is_array($cb) || !is_array($fe)) {
            return false;
        }

        $scheme = strtolower($cb['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        if (!isset($cb['host'], $fe['host'])) {
            return false;
        }

        // Host (and port) must match the trusted frontend origin.
        return strcasecmp($cb['host'], $fe['host']) === 0
            && ($cb['port'] ?? null) === ($fe['port'] ?? null);
    }
}
