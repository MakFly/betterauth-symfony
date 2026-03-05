<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251124163857 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add last_2fa_verified_at field to totp_data table for tracking daily 2FA verification';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('totp_data')) {
            return;
        }

        $table = $schema->getTable('totp_data');
        if (!$table->hasColumn('last2fa_verified_at')) {
            $table->addColumn('last2fa_verified_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('totp_data')) {
            return;
        }

        $table = $schema->getTable('totp_data');
        if ($table->hasColumn('last2fa_verified_at')) {
            $table->dropColumn('last2fa_verified_at');
        }
    }
}
