<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Token;

final readonly class RefreshRotationOutcome
{
    private function __construct(
        public RefreshRotationStatus $status,
        public ?TokenPair $tokens = null,
    ) {
    }

    public static function rotated(TokenPair $tokens): self
    {
        return new self(RefreshRotationStatus::Rotated, $tokens);
    }

    public static function replayed(): self
    {
        return new self(RefreshRotationStatus::Replayed);
    }

    public static function invalid(): self
    {
        return new self(RefreshRotationStatus::Invalid);
    }
}
