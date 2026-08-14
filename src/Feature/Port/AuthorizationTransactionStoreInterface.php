<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Feature\Port;

use BetterAuth\Symfony\Feature\AuthorizationTransaction;

interface AuthorizationTransactionStoreInterface
{
    public function store(AuthorizationTransaction $transaction): void;

    /**
     * Atomically return and consume exactly one transaction matching $purpose
     * and $state, only when it is unconsumed and unexpired at $now. A replay,
     * mismatched purpose, or expired state MUST return null.
     */
    public function consume(string $purpose, string $state, \DateTimeImmutable $now): ?AuthorizationTransaction;
}
