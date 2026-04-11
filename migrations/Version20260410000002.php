<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename table "user" to app_user to avoid PostgreSQL reserved keyword conflict.
 * "user" is a reserved word in PostgreSQL and causes "column t0.id does not exist" errors.
 */
final class Version20260410000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename "user" table to app_user (PostgreSQL reserved word fix)';
    }

    public function up(Schema $schema): void
    {
        if (!($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform)) {
            return;
        }

        // Check if app_user already exists (idempotent)
        $exists = $this->connection->executeQuery(
            "SELECT to_regclass('public.app_user')"
        )->fetchOne();

        if ($exists !== null) {
            // app_user already exists, nothing to do
            return;
        }

        // Check if "user" table exists
        $userExists = $this->connection->executeQuery(
            "SELECT to_regclass('public.user')"
        )->fetchOne();

        if ($userExists !== null) {
            // Rename the existing table
            $this->addSql('ALTER TABLE "user" RENAME TO app_user');
            $this->addSql('ALTER INDEX IF EXISTS UNIQ_USER_EMAIL RENAME TO UNIQ_APP_USER_EMAIL');
            $this->addSql('ALTER INDEX IF EXISTS IDX_USER_ATHLETE RENAME TO IDX_APP_USER_ATHLETE');
        } else {
            // Table doesn't exist at all - create it fresh
            $this->addSql('CREATE TABLE app_user (
                id SERIAL PRIMARY KEY,
                email VARCHAR(180) NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                roles JSON NOT NULL,
                password TEXT NOT NULL,
                linked_athlete_id INT REFERENCES athlete(id) ON DELETE SET NULL
            )');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_APP_USER_EMAIL ON app_user (email)');
            $this->addSql('CREATE INDEX IDX_APP_USER_ATHLETE ON app_user (linked_athlete_id)');
        }
    }

    public function down(Schema $schema): void
    {
        if (!($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform)) {
            return;
        }
        $this->addSql('ALTER TABLE app_user RENAME TO "user"');
        $this->addSql('ALTER INDEX IF EXISTS UNIQ_APP_USER_EMAIL RENAME TO UNIQ_USER_EMAIL');
        $this->addSql('ALTER INDEX IF EXISTS IDX_APP_USER_ATHLETE RENAME TO IDX_USER_ATHLETE');
    }
}
