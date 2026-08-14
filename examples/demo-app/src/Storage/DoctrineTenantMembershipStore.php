<?php

declare(strict_types=1);

namespace Demo\Storage;

use BetterAuth\Symfony\Feature\Port\TenantMembershipStoreInterface;
use Doctrine\DBAL\Connection;

final readonly class DoctrineTenantMembershipStore implements TenantMembershipStoreInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function hasMembership(string $userIdentifier, string $tenantIdentifier): bool
    {
        return $this->connection->fetchOne('SELECT 1 FROM demo_tenant_membership WHERE user_identifier = ? AND tenant_identifier = ?', [$userIdentifier, $tenantIdentifier]) !== false;
    }

    public function grant(string $userIdentifier, string $tenantIdentifier): void
    {
        $this->connection->executeStatement('INSERT INTO demo_tenant_membership (user_identifier, tenant_identifier) VALUES (?, ?) ON CONFLICT(user_identifier, tenant_identifier) DO NOTHING', [$userIdentifier, $tenantIdentifier]);
    }
}
