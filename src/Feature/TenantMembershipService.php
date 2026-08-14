<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Feature;

use BetterAuth\Symfony\Feature\Port\TenantMembershipStoreInterface;

final readonly class TenantMembershipService
{
    public function __construct(private TenantMembershipStoreInterface $store)
    {
    }

    public function allows(string $userIdentifier, string $tenantIdentifier): bool
    {
        return $this->store->hasMembership($userIdentifier, $tenantIdentifier);
    }
}
