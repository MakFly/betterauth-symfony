<?php

declare(strict_types=1);

namespace Demo\Storage;

final readonly class PendingTotpSnapshot
{
    public function __construct(public string $ciphertext)
    {
    }
}
