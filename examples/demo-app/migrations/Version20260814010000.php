<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260814010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create hash-only and ciphertext-only BetterAuth demonstration stores.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE demo_refresh_token (token_hash VARCHAR(64) NOT NULL, user_identifier VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, replaced_by_hash VARCHAR(64) DEFAULT NULL, revoked BOOLEAN NOT NULL DEFAULT 0, PRIMARY KEY(token_hash))');
        $this->addSql('CREATE INDEX IDX_DEMO_REFRESH_USER ON demo_refresh_token (user_identifier)');
        $this->addSql('CREATE TABLE demo_one_time_token (purpose VARCHAR(32) NOT NULL, token_hash VARCHAR(64) NOT NULL, user_identifier VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, consumed_at DATETIME DEFAULT NULL, PRIMARY KEY(purpose, token_hash))');
        $this->addSql('CREATE TABLE demo_totp_seed (user_identifier VARCHAR(64) NOT NULL, ciphertext TEXT NOT NULL, PRIMARY KEY(user_identifier))');
        $this->addSql('CREATE TABLE demo_device (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_identifier VARCHAR(64) NOT NULL, fingerprint VARCHAR(64) NOT NULL, user_agent TEXT DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, attributes TEXT NOT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('CREATE TABLE demo_security_event (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_identifier VARCHAR(64) NOT NULL, type VARCHAR(64) NOT NULL, severity VARCHAR(16) NOT NULL, details TEXT NOT NULL, occurred_at DATETIME NOT NULL)');
        $this->addSql('CREATE TABLE demo_tenant_membership (user_identifier VARCHAR(64) NOT NULL, tenant_identifier VARCHAR(64) NOT NULL, PRIMARY KEY(user_identifier, tenant_identifier))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE demo_tenant_membership');
        $this->addSql('DROP TABLE demo_security_event');
        $this->addSql('DROP TABLE demo_device');
        $this->addSql('DROP TABLE demo_totp_seed');
        $this->addSql('DROP TABLE demo_one_time_token');
        $this->addSql('DROP TABLE demo_refresh_token');
    }
}
