<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fix: ensure "user" table has correct schema (id column with proper name).
 * Recreates the user table if columns are misnamed (e.g. missing id column).
 */
final class Version20260410000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix user table schema: ensure correct column names for PostgreSQL';
    }

    public function up(Schema $schema): void
    {
        if (!($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform)) {
            return;
        }

        // Check if the id column exists in the user table
        $result = $this->connection->executeQuery(
            "SELECT column_name FROM information_schema.columns WHERE table_name = 'user' AND column_name = 'id'"
        );

        if ($result->fetchOne() !== false) {
            // id column exists, nothing to do
            return;
        }

        // The user table exists but with wrong column names — drop and recreate
        $this->addSql('DROP TABLE IF EXISTS "user" CASCADE');
        $this->addSql('CREATE TABLE "user" (
            id SERIAL PRIMARY KEY,
            email VARCHAR(180) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            roles JSON NOT NULL,
            password TEXT NOT NULL,
            linked_athlete_id INT REFERENCES athlete(id) ON DELETE SET NULL
        )');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_USER_EMAIL ON "user" (email)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_USER_ATHLETE ON "user" (linked_athlete_id)');
    }

    public function down(Schema $schema): void
    {
        // Cannot safely restore dropped data
    }
}
