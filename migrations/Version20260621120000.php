<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Migrations;

use BetterAuth\Core\Utils\Crypto;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add an opaque session id (decoupled from the secret token) to the sessions table.
 *
 * The column is nullable + unique so existing rows can be backfilled in postUp()
 * without violating the constraint (MySQL treats multiple NULLs as distinct).
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
            $table->addColumn('id', Types::STRING, ['length' => 64, 'notnull' => false]);
        }
        if (!$table->hasIndex('uniq_sessions_id')) {
            $table->addUniqueIndex(['id'], 'uniq_sessions_id');
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
            $table->dropIndex('uniq_sessions_id');
        }
        if ($table->hasColumn('id')) {
            $table->dropColumn('id');
        }
    }
}
