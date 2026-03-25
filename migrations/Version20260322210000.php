<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260322210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate athlete discipline from string to JSON array';
    }

    public function up(Schema $schema): void
    {
        // Convert existing single strings to JSON arrays (SQLite json_array support)
        $this->addSql("UPDATE athlete SET discipline = json_array(discipline) WHERE discipline IS NOT NULL AND discipline != '' AND discipline NOT LIKE '[%'");
        $this->addSql("UPDATE athlete SET discipline = '[]' WHERE discipline IS NULL OR discipline = ''");
    }

    public function down(Schema $schema): void
    {
        // Extract first element from JSON array back to plain string
        $this->addSql("UPDATE athlete SET discipline = json_extract(discipline, '$[0]') WHERE discipline LIKE '[%'");
    }
}
