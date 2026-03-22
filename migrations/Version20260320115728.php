<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260320115728 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE athlete_video (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, discipline VARCHAR(100) DEFAULT NULL, filename VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, athlete_id INTEGER NOT NULL, CONSTRAINT FK_7ACCC381FE6BCB8B FOREIGN KEY (athlete_id) REFERENCES athlete (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_7ACCC381FE6BCB8B ON athlete_video (athlete_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE athlete_video');
    }
}
