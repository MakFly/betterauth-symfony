<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Feature;

use BetterAuth\Symfony\Feature\Port\DeviceStoreInterface;

final readonly class DeviceService
{
    public function __construct(private DeviceStoreInterface $store)
    {
    }

    /** @param array<string, scalar|null> $attributes */
    public function record(string $userIdentifier, string $userAgent, string $ipAddress, array $attributes = []): string
    {
        ksort($attributes);
        $fingerprint = hash('sha256', implode('|', [$userAgent, $ipAddress, json_encode($attributes, JSON_THROW_ON_ERROR)]));
        $this->store->record($userIdentifier, ['fingerprint' => $fingerprint, 'user_agent' => $userAgent, 'ip_address' => $ipAddress] + $attributes);

        return $fingerprint;
    }
}
