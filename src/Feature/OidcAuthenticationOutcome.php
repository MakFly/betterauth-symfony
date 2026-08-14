<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Feature;

final readonly class OidcAuthenticationOutcome
{
    /** @param array<string, mixed>|null $claims */
    private function __construct(public OidcAuthenticationStatus $status, public ?string $subject, public ?array $claims)
    {
    }

    /** @param array<string, mixed> $claims */
    public static function authenticated(string $subject, array $claims): self
    {
        return new self(OidcAuthenticationStatus::Authenticated, $subject, $claims);
    }

    public static function invalid(): self
    {
        return new self(OidcAuthenticationStatus::Invalid, null, null);
    }
}
