<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260814000000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create local demonstration accounts.'; }
    public function up(Schema $schema): void { $this->addSql('CREATE TABLE demo_account (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(254) NOT NULL, password_hash VARCHAR(255) NOT NULL)'); $this->addSql('CREATE UNIQUE INDEX UNIQ_DEMO_ACCOUNT_EMAIL ON demo_account (email)'); }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE demo_account'); }
}
