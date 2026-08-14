<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Feature;

use BetterAuth\Symfony\Core\Utils\Crypto;
use BetterAuth\Symfony\Feature\Port\OneTimeTokenStoreInterface;

final readonly class OneTimeTokenService
{
    public function __construct(private OneTimeTokenStoreInterface $store, private string $purpose, private int $ttl)
    {
    }

    public function issue(string $userIdentifier): string
    {
        $rawToken = Crypto::randomToken(32);
        $this->store->store(
            $this->purpose,
            hash('sha256', $rawToken),
            $userIdentifier,
            new \DateTimeImmutable(sprintf('+%d seconds', $this->ttl)),
        );

        return $rawToken;
    }

    public function consume(string $rawToken): ?string
    {
        return $this->store->consume($this->purpose, hash('sha256', $rawToken), new \DateTimeImmutable());
    }
}
