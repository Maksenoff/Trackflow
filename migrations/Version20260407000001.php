<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute license_number sur athlete et ffa_registered sur competition_registration';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE athlete ADD COLUMN IF NOT EXISTS license_number VARCHAR(20) DEFAULT NULL');
            $this->addSql('ALTER TABLE competition_registration ADD COLUMN IF NOT EXISTS ffa_registered BOOLEAN NOT NULL DEFAULT FALSE');
            return;
        }

        // SQLite : vérifie l'existence des colonnes avant d'ajouter
        $athleteCols = array_column(
            $this->connection->executeQuery('PRAGMA table_info(athlete)')->fetchAllAssociative(),
            'name'
        );
        if (!in_array('license_number', $athleteCols)) {
            $this->connection->executeStatement('ALTER TABLE athlete ADD COLUMN license_number VARCHAR(20) DEFAULT NULL');
        }

        $regCols = array_column(
            $this->connection->executeQuery('PRAGMA table_info(competition_registration)')->fetchAllAssociative(),
            'name'
        );
        if (!in_array('ffa_registered', $regCols)) {
            $this->connection->executeStatement('ALTER TABLE competition_registration ADD COLUMN ffa_registered BOOLEAN NOT NULL DEFAULT 0');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE athlete DROP COLUMN IF EXISTS license_number');
            $this->addSql('ALTER TABLE competition_registration DROP COLUMN IF EXISTS ffa_registered');
        }
    }
}
