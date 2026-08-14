<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Feature\Port;

interface SecurityMonitoringStoreInterface
{
    /** @param array<string, mixed> $event */
    public function record(string $userIdentifier, array $event): void;
}
