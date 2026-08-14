<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260814020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pending, expiring TOTP enrollment storage.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demo_totp_seed ADD COLUMN pending_ciphertext TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE demo_totp_seed ADD COLUMN pending_expires_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demo_totp_seed DROP COLUMN pending_ciphertext');
        $this->addSql('ALTER TABLE demo_totp_seed DROP COLUMN pending_expires_at');
    }
}
