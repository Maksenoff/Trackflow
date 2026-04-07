<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Clean up orphaned competition_registration rows whose athlete no longer exists.
 * SQLite doesn't enforce ON DELETE CASCADE without PRAGMA foreign_keys=ON,
 * so these rows survive athlete deletion and cause getFullName()-on-null crashes.
 */
final class Version20260407000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Delete competition_registration rows referencing non-existent athletes';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            $this->connection->executeStatement(
                'DELETE FROM competition_registration
                 WHERE athlete_id IS NOT NULL
                   AND athlete_id NOT IN (SELECT id FROM athlete)'
            );
        } else {
            $this->addSql(
                'DELETE FROM competition_registration
                 WHERE athlete_id IS NOT NULL
                   AND athlete_id NOT IN (SELECT id FROM athlete)'
            );
        }
    }

    public function down(Schema $schema): void
    {
        // Cannot restore deleted rows
    }
}
