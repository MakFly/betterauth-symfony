<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Migrations;

use BetterAuth\Core\Utils\Crypto;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add an opaque session id (decoupled from the secret token) to the sessions table.
 *
 * The column is nullable + unique so existing rows can be backfilled in postUp()
 * without violating the constraint (MySQL treats multiple NULLs as distinct).
 *
 * NB: we read $schema only to stay idempotent, but emit raw SQL via addSql()
 * instead of mutating the Schema object. Mutating $schema makes Doctrine compute
 * a full-schema diff, which introspects EVERY table of the host application and
 * aborts on any unrelated schema drift there (a table the host dropped without a
 * migration, a dangling FK, …). Raw SQL touches only the sessions table.
 */
final class Version20260621120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add opaque id column to sessions table (revocation handle)';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('sessions')) {
            return;
        }

        $table = $schema->getTable('sessions');
        if (!$table->hasColumn('id')) {
            $this->addSql('ALTER TABLE sessions ADD id VARCHAR(64) DEFAULT NULL');
        }
        if (!$table->hasIndex('uniq_sessions_id')) {
            $this->addSql('CREATE UNIQUE INDEX uniq_sessions_id ON sessions (id)');
        }
    }

    /**
     * Backfill an opaque id for every pre-existing session row.
     */
    public function postUp(Schema $schema): void
    {
        if (!$schema->hasTable('sessions')) {
            return;
        }

        $tokens = $this->connection->fetchFirstColumn(
            'SELECT token FROM sessions WHERE id IS NULL'
        );

        foreach ($tokens as $token) {
            $this->connection->executeStatement(
                'UPDATE sessions SET id = ? WHERE token = ?',
                [Crypto::randomToken(16), $token]
            );
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('sessions')) {
            return;
        }

        $table = $schema->getTable('sessions');
        if ($table->hasIndex('uniq_sessions_id')) {
            $this->addSql(
                $this->platform instanceof AbstractMySQLPlatform
                    ? 'DROP INDEX uniq_sessions_id ON sessions'
                    : 'DROP INDEX uniq_sessions_id'
            );
        }
        if ($table->hasColumn('id')) {
            $this->addSql('ALTER TABLE sessions DROP id');
        }
    }
}
