<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251124104040 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create BetterAuth core tables (users, sessions, tokens)';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('email_verification_tokens')) {
            $table = $schema->createTable('email_verification_tokens');
            $table->addColumn('token', Types::STRING, ['length' => 255]);
            $table->addColumn('email', Types::STRING, ['length' => 255]);
            $table->addColumn('expires_at', Types::DATETIME_IMMUTABLE);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE);
            $table->addColumn('used', Types::BOOLEAN);
            $table->setPrimaryKey(['token']);
        }

        if (!$schema->hasTable('magic_link_tokens')) {
            $table = $schema->createTable('magic_link_tokens');
            $table->addColumn('token', Types::STRING, ['length' => 255]);
            $table->addColumn('email', Types::STRING, ['length' => 255]);
            $table->addColumn('expires_at', Types::DATETIME_IMMUTABLE);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE);
            $table->addColumn('used', Types::BOOLEAN);
            $table->setPrimaryKey(['token']);
        }

        if (!$schema->hasTable('password_reset_tokens')) {
            $table = $schema->createTable('password_reset_tokens');
            $table->addColumn('token', Types::STRING, ['length' => 255]);
            $table->addColumn('email', Types::STRING, ['length' => 255]);
            $table->addColumn('expires_at', Types::DATETIME_IMMUTABLE);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE);
            $table->addColumn('used', Types::BOOLEAN);
            $table->setPrimaryKey(['token']);
        }

        if (!$schema->hasTable('refresh_tokens')) {
            $table = $schema->createTable('refresh_tokens');
            $table->addColumn('token', Types::STRING, ['length' => 255]);
            $table->addColumn('user_id', Types::STRING, ['length' => 36]);
            $table->addColumn('expires_at', Types::DATETIME_IMMUTABLE);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE);
            $table->addColumn('revoked', Types::BOOLEAN);
            $table->addColumn('replaced_by', Types::STRING, ['length' => 255, 'notnull' => false]);
            $table->setPrimaryKey(['token']);
        }

        if (!$schema->hasTable('sessions')) {
            $table = $schema->createTable('sessions');
            $table->addColumn('token', Types::STRING, ['length' => 255]);
            $table->addColumn('user_id', Types::STRING, ['length' => 36]);
            $table->addColumn('expires_at', Types::DATETIME_IMMUTABLE);
            $table->addColumn('ip_address', Types::STRING, ['length' => 45]);
            $table->addColumn('user_agent', Types::STRING, ['length' => 500]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE);
            $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE);
            $table->addColumn('metadata', Types::JSON, ['notnull' => false]);
            $table->addColumn('active_organization_id', Types::STRING, ['length' => 36, 'notnull' => false]);
            $table->addColumn('active_team_id', Types::STRING, ['length' => 36, 'notnull' => false]);
            $table->setPrimaryKey(['token']);
        }

        if (!$schema->hasTable('totp_data')) {
            $table = $schema->createTable('totp_data');
            $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
            $table->addColumn('user_id', Types::STRING, ['length' => 255]);
            $table->addColumn('secret', Types::STRING, ['length' => 255]);
            $table->addColumn('enabled', Types::BOOLEAN);
            $table->addColumn('backup_codes', Types::JSON);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['user_id'], 'uniq_totp_data_user_id');
        }

        if (!$schema->hasTable('users')) {
            $table = $schema->createTable('users');
            $table->addColumn('id', Types::STRING, ['length' => 36]);
            $table->addColumn('email', Types::STRING, ['length' => 255]);
            $table->addColumn('password_hash', Types::STRING, ['length' => 255, 'notnull' => false]);
            $table->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => false]);
            $table->addColumn('avatar', Types::STRING, ['length' => 500, 'notnull' => false]);
            $table->addColumn('email_verified', Types::BOOLEAN);
            $table->addColumn('email_verified_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE);
            $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE);
            $table->addColumn('metadata', Types::JSON, ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['email'], 'uniq_users_email');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('email_verification_tokens')) {
            $schema->dropTable('email_verification_tokens');
        }
        if ($schema->hasTable('magic_link_tokens')) {
            $schema->dropTable('magic_link_tokens');
        }
        if ($schema->hasTable('password_reset_tokens')) {
            $schema->dropTable('password_reset_tokens');
        }
        if ($schema->hasTable('refresh_tokens')) {
            $schema->dropTable('refresh_tokens');
        }
        if ($schema->hasTable('sessions')) {
            $schema->dropTable('sessions');
        }
        if ($schema->hasTable('totp_data')) {
            $schema->dropTable('totp_data');
        }
        if ($schema->hasTable('users')) {
            $schema->dropTable('users');
        }
    }
}
