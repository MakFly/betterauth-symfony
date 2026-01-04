<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\License;

/**
 * Value object representing license information.
 */
final readonly class LicenseInfo
{
    public const TIER_FREE = 'free';
    public const TIER_PRO = 'pro';
    public const TIER_ENTERPRISE = 'enterprise';

    /**
     * @param string $tier License tier (free, pro, enterprise)
     * @param array<string> $features Enabled features
     * @param \DateTimeImmutable|null $expiresAt License expiration date
     * @param int $activationCount Number of current activations
     * @param int $maxActivations Maximum allowed activations
     * @param bool $isValid Whether the license is currently valid
     * @param string|null $reason Reason for invalid status
     */
    public function __construct(
        public string $tier,
        public array $features,
        public ?\DateTimeImmutable $expiresAt,
        public int $activationCount,
        public int $maxActivations,
        public bool $isValid,
        public ?string $reason = null,
    ) {
    }

    /**
     * Create a free tier license info.
     */
    public static function free(): self
    {
        return new self(
            tier: self::TIER_FREE,
            features: ['email_password', 'sessions', 'password_reset'],
            expiresAt: null,
            activationCount: 0,
            maxActivations: PHP_INT_MAX,
            isValid: true,
        );
    }

    /**
     * Check if a feature is enabled.
     */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features, true);
    }

    /**
     * Check if license is expired.
     */
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new \DateTimeImmutable();
    }

    /**
     * Check if activation limit is reached.
     */
    public function isActivationLimitReached(): bool
    {
        return $this->activationCount >= $this->maxActivations;
    }

    /**
     * Get days until expiration.
     */
    public function getDaysUntilExpiration(): ?int
    {
        if ($this->expiresAt === null) {
            return null;
        }

        $now = new \DateTimeImmutable();
        $diff = $now->diff($this->expiresAt);

        return $diff->invert ? -$diff->days : $diff->days;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tier' => $this->tier,
            'features' => $this->features,
            'expires_at' => $this->expiresAt?->format('Y-m-d H:i:s'),
            'activation_count' => $this->activationCount,
            'max_activations' => $this->maxActivations,
            'is_valid' => $this->isValid,
            'reason' => $this->reason,
        ];
    }
}
