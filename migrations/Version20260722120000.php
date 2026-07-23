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
 * NB: we read $schema only to stay idempotent (skip absent tables/columns, skip
 * existing indexes), but emit raw SQL via addSql() instead of mutating the Schema
 * object. Mutating $schema (addIndex/dropIndex) makes Doctrine compute a full-schema
 * diff, which introspects EVERY table of the host application and aborts on any
 * unrelated schema drift there (a table the host dropped without a migration, a
 * dangling FK, …). Raw SQL touches only the token tables. Same fix as
 * Version20260621120000.
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
        foreach (self::INDEXES as $tableName => $columns) {
            if (!$schema->hasTable($tableName)) {
                continue;
            }
            $table = $schema->getTable($tableName);
            foreach ($columns as $column) {
                $indexName = "idx_{$tableName}_{$column}";
                if (!$table->hasColumn($column) || $table->hasIndex($indexName)) {
                    continue;
                }
                $this->addSql("CREATE INDEX {$indexName} ON {$tableName} ({$column})");
            }
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::INDEXES as $tableName => $columns) {
            if (!$schema->hasTable($tableName)) {
                continue;
            }
            $table = $schema->getTable($tableName);
            foreach ($columns as $column) {
                $indexName = "idx_{$tableName}_{$column}";
                if ($table->hasIndex($indexName)) {
                    $this->addSql(
                        $this->platform instanceof AbstractMySQLPlatform
                            ? "DROP INDEX {$indexName} ON {$tableName}"
                            : "DROP INDEX {$indexName}"
                    );
                }
            }
        }
    }
}
