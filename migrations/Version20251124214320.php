<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251124214320 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE guest_sessions (id VARCHAR(36) NOT NULL, token VARCHAR(64) NOT NULL, device_info TEXT DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, metadata JSON DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E54A556C5F37A13B ON guest_sessions (token)');
        $this->addSql('CREATE INDEX idx_guest_sessions_token ON guest_sessions (token)');
        $this->addSql('CREATE INDEX idx_guest_sessions_expires_at ON guest_sessions (expires_at)');
        $this->addSql('ALTER TABLE totp_data ADD last2fa_verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE guest_sessions');
        $this->addSql('ALTER TABLE totp_data DROP last2fa_verified_at');
    }
}
