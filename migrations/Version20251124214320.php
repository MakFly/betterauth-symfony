<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251124214320 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add guest sessions table';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('guest_sessions')) {
            $table = $schema->createTable('guest_sessions');
            $table->addColumn('id', Types::STRING, ['length' => 36]);
            $table->addColumn('token', Types::STRING, ['length' => 64]);
            $table->addColumn('device_info', Types::TEXT, ['notnull' => false]);
            $table->addColumn('ip_address', Types::STRING, ['length' => 45, 'notnull' => false]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE);
            $table->addColumn('expires_at', Types::DATETIME_IMMUTABLE);
            $table->addColumn('metadata', Types::JSON, ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['token'], 'uniq_guest_sessions_token');
            $table->addIndex(['expires_at'], 'idx_guest_sessions_expires_at');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('guest_sessions')) {
            $schema->dropTable('guest_sessions');
        }
    }
}
