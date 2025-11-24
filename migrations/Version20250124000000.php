<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250124000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create BetterAuth token tables (magic_link_tokens, email_verification_tokens, password_reset_tokens)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE IF NOT EXISTS magic_link_tokens (
            token VARCHAR(255) NOT NULL PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP NOT NULL,
            used BOOLEAN NOT NULL DEFAULT FALSE
        )');

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_magic_link_email ON magic_link_tokens (email)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_magic_link_expires ON magic_link_tokens (expires_at)');

        $this->addSql('CREATE TABLE IF NOT EXISTS email_verification_tokens (
            token VARCHAR(255) NOT NULL PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP NOT NULL,
            used BOOLEAN NOT NULL DEFAULT FALSE
        )');

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_email_verification_email ON email_verification_tokens (email)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_email_verification_expires ON email_verification_tokens (expires_at)');

        $this->addSql('CREATE TABLE IF NOT EXISTS password_reset_tokens (
            token VARCHAR(255) NOT NULL PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP NOT NULL,
            used BOOLEAN NOT NULL DEFAULT FALSE
        )');

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_password_reset_email ON password_reset_tokens (email)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_password_reset_expires ON password_reset_tokens (expires_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE IF EXISTS magic_link_tokens');
        $this->addSql('DROP TABLE IF EXISTS email_verification_tokens');
        $this->addSql('DROP TABLE IF EXISTS password_reset_tokens');
    }
}
