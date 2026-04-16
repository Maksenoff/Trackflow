<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename "user" table to app_user for SQLite (local dev).
 * PostgreSQL already has app_user from Version20260410000002 — no-op.
 */
final class Version20260416000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename "user" table to app_user for SQLite (local dev)';
    }

    public function up(Schema $schema): void
    {
        // PostgreSQL already handled by Version20260410000002
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return;
        }

        // SQLite: check if "user" table still exists and "app_user" doesn't
        $tables = $this->connection->createSchemaManager()->listTableNames();
        if (in_array('user', $tables) && !in_array('app_user', $tables)) {
            $this->addSql('ALTER TABLE "user" RENAME TO app_user');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return;
        }

        $tables = $this->connection->createSchemaManager()->listTableNames();
        if (in_array('app_user', $tables) && !in_array('user', $tables)) {
            $this->addSql('ALTER TABLE app_user RENAME TO "user"');
        }
    }
}
