<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260324120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add competition_type and competition tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE competition_type (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(80) NOT NULL, color VARCHAR(7) NOT NULL)');
        $this->addSql('CREATE TABLE competition (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, competition_type_id INTEGER DEFAULT NULL, title VARCHAR(150) NOT NULL, location VARCHAR(200) DEFAULT NULL, date DATE NOT NULL, document_filename VARCHAR(255) DEFAULT NULL, website_url VARCHAR(500) DEFAULT NULL, description CLOB DEFAULT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_B50A2CB156E96A7B FOREIGN KEY (competition_type_id) REFERENCES competition_type (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_B50A2CB156E96A7B ON competition (competition_type_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE competition');
        $this->addSql('DROP TABLE competition_type');
    }
}
