<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\License\Attribute;

use Attribute;

/**
 * Attribute to mark controllers/methods that require a specific license tier.
 *
 * Usage:
 * ```php
 * #[RequiresLicense(tier: 'pro', feature: 'oauth')]
 * class OAuthController extends AbstractController
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class RequiresLicense
{
    /**
     * @param string $tier Required license tier (pro, enterprise)
     * @param string|null $feature Specific feature to check
     */
    public function __construct(
        public string $tier = 'pro',
        public ?string $feature = null,
    ) {
    }
}
