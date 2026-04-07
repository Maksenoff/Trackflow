<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Clean up stale linked_athlete_id references on the user table.
 * When an athlete is deleted and SQLite FK enforcement is off,
 * user rows keep the old ID — this nullifies any such orphaned references.
 */
final class Version20260407000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Nullify user.linked_athlete_id references to non-existent athletes';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            $this->connection->executeStatement(
                'UPDATE user SET linked_athlete_id = NULL
                 WHERE linked_athlete_id IS NOT NULL
                   AND linked_athlete_id NOT IN (SELECT id FROM athlete)'
            );
        } else {
            // PostgreSQL / MySQL — same query works
            $this->addSql(
                'UPDATE "user" SET linked_athlete_id = NULL
                 WHERE linked_athlete_id IS NOT NULL
                   AND linked_athlete_id NOT IN (SELECT id FROM athlete)'
            );
        }
    }

    public function down(Schema $schema): void
    {
        // Cannot restore deleted references
    }
}
