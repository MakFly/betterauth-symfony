<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Feature\Port;

interface TotpStoreInterface
{
    /** Stores authenticated ciphertext only; never persist a raw TOTP seed. */
    public function save(string $userIdentifier, string $ciphertext): void;

    /** Returns the authenticated ciphertext, or null when no enrollment exists. */
    public function load(string $userIdentifier): ?string;
}
