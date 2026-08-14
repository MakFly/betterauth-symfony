<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Feature;

use BetterAuth\Symfony\Feature\Port\SecurityMonitoringStoreInterface;

final readonly class SecurityMonitoringService
{
    public function __construct(private SecurityMonitoringStoreInterface $store)
    {
    }

    /** @param array<string, mixed> $details */
    public function record(string $userIdentifier, string $type, string $severity, array $details = []): void
    {
        $this->store->record($userIdentifier, [
            'type' => $type,
            'severity' => $severity,
            'details' => $details,
            'occurred_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }
}
