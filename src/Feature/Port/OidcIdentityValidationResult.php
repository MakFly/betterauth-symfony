<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Feature\Port;

final readonly class OidcIdentityValidationResult
{
    /** @param array<string, mixed>|null $claims */
    private function __construct(public OidcIdentityValidationStatus $status, public ?string $subject, public ?array $claims)
    {
    }

    /** @param array<string, mixed> $claims */
    public static function valid(string $subject, array $claims): self
    {
        return new self(OidcIdentityValidationStatus::Valid, $subject, $claims);
    }

    public static function invalid(): self
    {
        return new self(OidcIdentityValidationStatus::Invalid, null, null);
    }
}
