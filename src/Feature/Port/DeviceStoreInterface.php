<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Feature\Port;

interface DeviceStoreInterface
{
    /** @param array<string, scalar|null> $device */
    public function record(string $userIdentifier, array $device): void;
}
