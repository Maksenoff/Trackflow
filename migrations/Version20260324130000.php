<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260324130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Insert default competition types';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO competition_type (name, color) VALUES ('Indoor',        '#6366f1')");
        $this->addSql("INSERT INTO competition_type (name, color) VALUES ('Meeting',       '#10b981')");
        $this->addSql("INSERT INTO competition_type (name, color) VALUES ('Départementaux','#f59e0b')");
        $this->addSql("INSERT INTO competition_type (name, color) VALUES ('Régionaux',     '#3b82f6')");
        $this->addSql("INSERT INTO competition_type (name, color) VALUES ('Outdoor',       '#ec4899')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM competition_type WHERE name IN ('Indoor','Meeting','Départementaux','Régionaux','Outdoor')");
    }
}
