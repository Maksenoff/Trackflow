<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260323213932 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user table with authentication and athlete link';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE "user" (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            linked_athlete_id INTEGER DEFAULT NULL,
            email VARCHAR(180) NOT NULL,
            roles CLOB NOT NULL,
            password VARCHAR(255) NOT NULL,
            CONSTRAINT UNIQ_IDENTIFIER_EMAIL UNIQUE (email),
            CONSTRAINT UNIQ_LINKED_ATHLETE UNIQUE (linked_athlete_id),
            CONSTRAINT FK_LINKED_ATHLETE FOREIGN KEY (linked_athlete_id) REFERENCES athlete (id) ON DELETE SET NULL
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE "user"');
    }
}
