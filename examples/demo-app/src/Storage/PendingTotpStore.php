<?php

declare(strict_types=1);

namespace Demo\Storage;

use BetterAuth\Symfony\Feature\Port\TotpStoreInterface;

final readonly class PendingTotpStore implements TotpStoreInterface
{
    public function __construct(private DoctrineTotpStore $store)
    {
    }

    public function save(string $userIdentifier, string $ciphertext): void
    {
        $this->store->savePending($userIdentifier, $ciphertext, new \DateTimeImmutable('+10 minutes'));
    }

    public function load(string $userIdentifier): ?string
    {
        return $this->store->loadPending($userIdentifier, new \DateTimeImmutable());
    }
}
