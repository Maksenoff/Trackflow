<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260325200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create competition_registration table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE competition_registration (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            athlete_id INTEGER NOT NULL,
            competition_id INTEGER NOT NULL,
            disciplines CLOB NOT NULL,
            registered_at DATETIME NOT NULL,
            CONSTRAINT uq_athlete_competition UNIQUE (athlete_id, competition_id),
            CONSTRAINT fk_reg_athlete FOREIGN KEY (athlete_id) REFERENCES athlete (id) ON DELETE CASCADE,
            CONSTRAINT fk_reg_competition FOREIGN KEY (competition_id) REFERENCES competition (id) ON DELETE CASCADE
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE competition_registration');
    }
}
