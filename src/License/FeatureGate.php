<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\License;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Feature gate service for checking premium feature access.
 *
 * Provides soft gating (feature disabled) instead of hard errors,
 * improving user experience during license transitions.
 */
final class FeatureGate
{
    /**
     * Features available per tier.
     */
    private const TIER_FEATURES = [
        LicenseInfo::TIER_FREE => [
            'email_password',
            'sessions',
            'password_reset',
        ],
        LicenseInfo::TIER_PRO => [
            'email_password',
            'sessions',
            'password_reset',
            'oauth',
            '2fa',
            'totp',
            'magic_link',
            'email_verification',
            'passkeys',
            'account_linking',
        ],
        LicenseInfo::TIER_ENTERPRISE => [
            'email_password',
            'sessions',
            'password_reset',
            'oauth',
            '2fa',
            'totp',
            'magic_link',
            'email_verification',
            'passkeys',
            'account_linking',
            'device_management',
            'organizations',
            'audit_logging',
            'threat_detection',
            'sso',
            'custom_branding',
        ],
    ];

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly LicenseValidator $validator,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Check if a feature is enabled for the current license.
     */
    public function isFeatureEnabled(string $feature): bool
    {
        $licenseInfo = $this->validator->validate();

        // Check if license is valid
        if (!$licenseInfo->isValid) {
            $this->logger->debug('Feature check failed: invalid license', [
                'feature' => $feature,
                'reason' => $licenseInfo->reason,
            ]);

            // Allow free features even with invalid license
            return in_array($feature, self::TIER_FEATURES[LicenseInfo::TIER_FREE], true);
        }

        // Check if feature is included in license
        $hasFeature = $licenseInfo->hasFeature($feature);

        $this->logger->debug('Feature check', [
            'feature' => $feature,
            'enabled' => $hasFeature,
            'tier' => $licenseInfo->tier,
        ]);

        return $hasFeature;
    }

    /**
     * Check if tier has access to a feature.
     */
    public function tierHasFeature(string $tier, string $feature): bool
    {
        $tierFeatures = self::TIER_FEATURES[$tier] ?? [];

        return in_array($feature, $tierFeatures, true);
    }

    /**
     * Get all enabled features for the current license.
     *
     * @return array<string>
     */
    public function getEnabledFeatures(): array
    {
        $licenseInfo = $this->validator->validate();

        if (!$licenseInfo->isValid) {
            return self::TIER_FEATURES[LicenseInfo::TIER_FREE];
        }

        return $licenseInfo->features;
    }

    /**
     * Get features available for a tier.
     *
     * @return array<string>
     */
    public function getTierFeatures(string $tier): array
    {
        return self::TIER_FEATURES[$tier] ?? [];
    }

    /**
     * Require a feature, throwing exception if not available.
     *
     * @throws LicenseFeatureException
     */
    public function requireFeature(string $feature): void
    {
        if (!$this->isFeatureEnabled($feature)) {
            $licenseInfo = $this->validator->validate();

            throw new LicenseFeatureException(
                sprintf(
                    'Feature "%s" requires a %s license. Current tier: %s',
                    $feature,
                    $this->getRequiredTierForFeature($feature),
                    $licenseInfo->tier
                ),
                $feature,
                $licenseInfo->tier
            );
        }
    }

    /**
     * Get the minimum tier required for a feature.
     */
    public function getRequiredTierForFeature(string $feature): string
    {
        foreach ([LicenseInfo::TIER_FREE, LicenseInfo::TIER_PRO, LicenseInfo::TIER_ENTERPRISE] as $tier) {
            if (in_array($feature, self::TIER_FEATURES[$tier], true)) {
                return $tier;
            }
        }

        return LicenseInfo::TIER_ENTERPRISE;
    }

    /**
     * Get current license info.
     */
    public function getLicenseInfo(): LicenseInfo
    {
        return $this->validator->validate();
    }
}
