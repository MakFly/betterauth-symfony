<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Feature\Port;

interface TenantMembershipStoreInterface
{
    public function hasMembership(string $userIdentifier, string $tenantIdentifier): bool;
}
