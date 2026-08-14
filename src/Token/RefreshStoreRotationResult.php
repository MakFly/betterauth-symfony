<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Token;

final readonly class RefreshStoreRotationResult
{
    private function __construct(
        public RefreshRotationStatus $status,
        public ?RefreshTokenRecord $record = null,
    ) {
    }

    public static function rotated(RefreshTokenRecord $record): self
    {
        return new self(RefreshRotationStatus::Rotated, $record);
    }

    public static function replayed(RefreshTokenRecord $record): self
    {
        return new self(RefreshRotationStatus::Replayed, $record);
    }

    public static function invalid(): self
    {
        return new self(RefreshRotationStatus::Invalid);
    }
}
