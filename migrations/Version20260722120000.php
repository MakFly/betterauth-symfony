<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * SEC-30 — add missing indexes on token tables.
 *
 * Without these, "revoke all tokens for a user" and the cleanup commands scan the
 * whole table, degrading as data grows (and enabling a slow-query DoS).
 *
 * IMPORTANT: this migration MUST NOT touch the $schema argument — not even a read
 * such as $schema->hasTable(). Doctrine wraps it in a LazySchemaDiffProvider: the
 * first access realizes the proxy and makes the executor compute a full-schema diff
 * (Comparator::compareSchemas) over EVERY table of the host application. On a host
 * whose schema has any drift, that comparison aborts with a spurious
 * "There is no table with name … in the schema" (TableDoesNotExist) on an unrelated
 * host table — which is exactly what broke a production deploy.
 *
 * We therefore inspect the live connection's schema manager, scoped to the token
 * tables only, and emit raw SQL via addSql(). This never realizes the diff. Same
 * spirit as Version20260621120000.
 */
final class Version20260722120000 extends AbstractMigration
{
    /** @var array<string, array<string>> table => columns to index */
    private const INDEXES = [
        'refresh_tokens' => ['user_id', 'expires_at'],
        'sessions' => ['user_id', 'expires_at'],
        'email_verification_tokens' => ['expires_at'],
        'magic_link_tokens' => ['expires_at'],
        'password_reset_tokens' => ['expires_at'],
    ];

    public function getDescription(): string
    {
        return 'Add user_id / expires_at indexes on token tables (SEC-30)';
    }

    public function up(Schema $schema): void
    {
        $manager = $this->connection->createSchemaManager();
        $tables = $manager->listTableNames();

        foreach (self::INDEXES as $tableName => $columns) {
            if (!in_array($tableName, $tables, true)) {
                continue;
            }
            $indexes = $manager->listTableIndexes($tableName);
            foreach ($columns as $column) {
                $indexName = "idx_{$tableName}_{$column}";
                if (isset($indexes[$indexName])) {
                    continue;
                }
                $this->addSql("CREATE INDEX {$indexName} ON {$tableName} ({$column})");
            }
        }
    }

    public function down(Schema $schema): void
    {
        $manager = $this->connection->createSchemaManager();
        $tables = $manager->listTableNames();

        foreach (self::INDEXES as $tableName => $columns) {
            if (!in_array($tableName, $tables, true)) {
                continue;
            }
            $indexes = $manager->listTableIndexes($tableName);
            foreach ($columns as $column) {
                $indexName = "idx_{$tableName}_{$column}";
                if (!isset($indexes[$indexName])) {
                    continue;
                }
                $this->addSql(
                    $this->platform instanceof AbstractMySQLPlatform
                        ? "DROP INDEX {$indexName} ON {$tableName}"
                        : "DROP INDEX {$indexName}"
                );
            }
        }
    }
}
