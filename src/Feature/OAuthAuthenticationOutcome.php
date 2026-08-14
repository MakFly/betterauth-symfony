<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Feature;

final readonly class OAuthAuthenticationOutcome
{
    /** @param array<string, mixed>|null $identity */
    private function __construct(public OAuthAuthenticationStatus $status, public ?array $identity)
    {
    }

    /** @param array<string, mixed> $identity */
    public static function completed(array $identity): self
    {
        return new self(OAuthAuthenticationStatus::Completed, $identity);
    }

    public static function invalid(): self
    {
        return new self(OAuthAuthenticationStatus::Invalid, null);
    }
}
