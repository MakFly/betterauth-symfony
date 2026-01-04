<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\License;

/**
 * Exception thrown when a feature requires a higher license tier.
 */
final class LicenseFeatureException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $feature,
        public readonly string $currentTier,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the required tier for the feature.
     */
    public function getRequiredTier(): string
    {
        // Determine required tier based on feature
        return match ($this->feature) {
            'oauth', '2fa', 'totp', 'magic_link', 'email_verification', 'passkeys', 'account_linking' => LicenseInfo::TIER_PRO,
            'device_management', 'organizations', 'audit_logging', 'threat_detection', 'sso', 'custom_branding' => LicenseInfo::TIER_ENTERPRISE,
            default => LicenseInfo::TIER_PRO,
        };
    }

    /**
     * Get upgrade URL.
     */
    public function getUpgradeUrl(): string
    {
        return 'https://betterauth.dev/pricing';
    }
}
