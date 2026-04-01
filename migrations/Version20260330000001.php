<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Schéma initial TrackFlow — compatible SQLite (dev) et PostgreSQL (prod).
     * Chaque CREATE TABLE est protégé par IF NOT EXISTS pour être idempotent.
     */
final class Version20260330000001 extends AbstractMigration
    {
            public function getDescription(): string
        {
                    return 'Schéma initial TrackFlow (SQLite + PostgreSQL) — idempotent';
        }

    public function up(Schema $schema): void
        {
                    if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                                    // PostgreSQL : SQL natif avec IF NOT EXISTS
                        $this->addSql('CREATE TABLE IF NOT EXISTS training_type (id SERIAL PRIMARY KEY, name VARCHAR(80) NOT NULL, color VARCHAR(7) NOT NULL DEFAULT \'#6366f1\')');
                                    $this->addSql('CREATE TABLE IF NOT EXISTS competition_type (id SERIAL PRIMARY KEY, name VARCHAR(80) NOT NULL, color VARCHAR(7) NOT NULL DEFAULT \'#f59e0b\')');
                                    $this->addSql('CREATE TABLE IF NOT EXISTS athlete (id SERIAL PRIMARY KEY, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, birth_date DATE, gender VARCHAR(20), discipline JSON NOT NULL, notes TEXT, photo VARCHAR(255), ffa_profile_url VARCHAR(500), last_synced_at TIMESTAMP, created_at TIMESTAMP NOT NULL)');
                                    $this->addSql('CREATE TABLE IF NOT EXISTS session (id SERIAL PRIMARY KEY, title VARCHAR(150) NOT NULL, date DATE NOT NULL, description TEXT, duration_minutes INT, training_type_id INT REFERENCES training_type(id) ON DELETE SET NULL)');
                                    $this->addSql('CREATE INDEX IF NOT EXISTS IDX_SESSION_TRAINING_TYPE ON session (training_type_id)');
                                    $this->addSql('CREATE TABLE IF NOT EXISTS competition (id SERIAL PRIMARY KEY, title VARCHAR(150) NOT NULL, location VARCHAR(200), date DATE NOT NULL, competition_type_id INT REFERENCES competition_type(id) ON DELETE SET NULL, document_filename VARCHAR(255), website_url VARCHAR(500), description TEXT, created_at TIMESTAMP NOT NULL)');
                                    $this->addSql('CREATE INDEX IF NOT EXISTS IDX_COMPETITION_TYPE ON competition (competition_type_id)');
                                    $this->addSql('CREATE TABLE IF NOT EXISTS performance (id SERIAL PRIMARY KEY, athlete_id INT NOT NULL REFERENCES athlete(id) ON DELETE CASCADE, session_id INT REFERENCES session(id) ON DELETE SET NULL, discipline VARCHAR(50) NOT NULL, value NUMERIC(10,3) NOT NULL, unit VARCHAR(20) NOT NULL, notes TEXT, recorded_at DATE NOT NULL, is_personal_best BOOLEAN DEFAULT FALSE, is_competition BOOLEAN DEFAULT FALSE, is_indoor BOOLEAN, venue VARCHAR(150), level VARCHAR(15), level_points INT, wind VARCHAR(10))');
                                    $this->addSql('CREATE INDEX IF NOT EXISTS IDX_PERF_ATHLETE ON performance (athlete_id)');
                                    $this->addSql('CREATE INDEX IF NOT EXISTS IDX_PERF_SESSION ON performance (session_id)');
                                    $this->addSql('CREATE TABLE IF NOT EXISTS goal (id SERIAL PRIMARY KEY, athlete_id INT NOT NULL REFERENCES athlete(id) ON DELETE CASCADE, title VARCHAR(255) NOT NULL, discipline VARCHAR(50), target_value NUMERIC(10,3), unit VARCHAR(20), deadline DATE, status VARCHAR(20) NOT NULL DEFAULT \'in_progress\', notes TEXT, created_at TIMESTAMP NOT NULL)');
                                    $this->addSql('CREATE INDEX IF NOT EXISTS IDX_GOAL_ATHLETE ON goal (athlete_id)');
                                    $this->addSql('CREATE TABLE IF NOT EXISTS athlete_session (id SERIAL PRIMARY KEY, athlete_id INT NOT NULL REFERENCES athlete(id) ON DELETE CASCADE, session_id INT NOT NULL REFERENCES session(id) ON DELETE CASCADE, comment TEXT, difficulty INT, logged_at TIMESTAMP NOT NULL)');
                                    $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS athlete_session_unique ON athlete_session (athlete_id, session_id)');
                                    $this->addSql('CREATE INDEX IF NOT EXISTS IDX_AS_ATHLETE ON athlete_session (athlete_id)');
                                    $this->addSql('CREATE INDEX IF NOT EXISTS IDX_AS_SESSION ON athlete_session (session_id)');
                                    $this->addSql('CREATE TABLE IF NOT EXISTS athlete_video (id SERIAL PRIMARY KEY, athlete_id INT NOT NULL REFERENCES athlete(id) ON DELETE CASCADE, title VARCHAR(255) NOT NULL, discipline VARCHAR(100), filename VARCHAR(255) NOT NULL, created_at TIMESTAMP NOT NULL)');
                                    $this->addSql('CREATE INDEX IF NOT EXISTS IDX_AV_ATHLETE ON athlete_video (athlete_id)');
                                    $this->addSql('CREATE TABLE IF NOT EXISTS competition_registration (id SERIAL PRIMARY KEY, athlete_id INT NOT NULL REFERENCES athlete(id) ON DELETE CASCADE, competition_id INT NOT NULL REFERENCES competition(id) ON DELETE CASCADE, disciplines JSON NOT NULL, registered_at TIMESTAMP NOT NULL)');
                                    $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uq_athlete_competition ON competition_registration (athlete_id, competition_id)');
                                    $this->addSql('CREATE INDEX IF NOT EXISTS IDX_CR_ATHLETE ON competition_registration (athlete_id)');
                                    $this->addSql('CREATE INDEX IF NOT EXISTS IDX_CR_COMPETITION ON competition_registration (competition_id)');
                                    $this->addSql('CREATE TABLE IF NOT EXISTS "user" (id SERIAL PRIMARY KEY, email VARCHAR(180) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, roles JSON NOT NULL, password TEXT NOT NULL, linked_athlete_id INT REFERENCES athlete(id) ON DELETE SET NULL)');
                                    $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_USER_EMAIL ON "user" (email)');
                                    $this->addSql('CREATE INDEX IF NOT EXISTS IDX_USER_ATHLETE ON "user" (linked_athlete_id)');
                    } else {
                                    // SQLite : API Schema Doctrine (cross-platform)
                        if ($schema->hasTable('athlete')) {
                                            return;
                        }

                        $tt = $schema->createTable('training_type');
                                    $tt->addColumn('id', 'integer', ['autoincrement' => true]);
                                    $tt->addColumn('name', 'string', ['length' => 80]);
                                    $tt->addColumn('color', 'string', ['length' => 7, 'default' => '#6366f1']);
                                    $tt->setPrimaryKey(['id']);

                        $ct = $schema->createTable('competition_type');
                                    $ct->addColumn('id', 'integer', ['autoincrement' => true]);
                                    $ct->addColumn('name', 'string', ['length' => 80]);
                                    $ct->addColumn('color', 'string', ['length' => 7, 'default' => '#f59e0b']);
                                    $ct->setPrimaryKey(['id']);

                        $a = $schema->createTable('athlete');
                                    $a->addColumn('id', 'integer', ['autoincrement' => true]);
                                    $a->addColumn('first_name', 'string', ['length' => 100]);
                                    $a->addColumn('last_name', 'string', ['length' => 100]);
                                    $a->addColumn('birth_date', 'date', ['notnull' => false]);
                                    $a->addColumn('gender', 'string', ['length' => 20, 'notnull' => false]);
                                    $a->addColumn('discipline', 'json', []);
                                    $a->addColumn('notes', 'text', ['notnull' => false]);
                                    $a->addColumn('photo', 'string', ['length' => 255, 'notnull' => false]);
                                    $a->addColumn('ffa_profile_url', 'string', ['length' => 500, 'notnull' => false]);
                                    $a->addColumn('last_synced_at', 'datetime', ['notnull' => false]);
                                    $a->addColumn('created_at', 'datetime', []);
                                    $a->setPrimaryKey(['id']);

                        $s = $schema->createTable('session');
                                    $s->addColumn('id', 'integer', ['autoincrement' => true]);
                                    $s->addColumn('title', 'string', ['length' => 150]);
                                    $s->addColumn('date', 'date', []);
                                    $s->addColumn('description', 'text', ['notnull' => false]);
                                    $s->addColumn('duration_minutes', 'integer', ['notnull' => false]);
                                    $s->addColumn('training_type_id', 'integer', ['notnull' => false]);
                                    $s->setPrimaryKey(['id']);

                        $c = $schema->createTable('competition');
                                    $c->addColumn('id', 'integer', ['autoincrement' => true]);
                                    $c->addColumn('title', 'string', ['length' => 150]);
                                    $c->addColumn('location', 'string', ['length' => 200, 'notnull' => false]);
                                    $c->addColumn('date', 'date', []);
                                    $c->addColumn('competition_type_id', 'integer', ['notnull' => false]);
                                    $c->addColumn('document_filename', 'string', ['length' => 255, 'notnull' => false]);
                                    $c->addColumn('website_url', 'string', ['length' => 500, 'notnull' => false]);
                                    $c->addColumn('description', 'text', ['notnull' => false]);
                                    $c->addColumn('created_at', 'datetime', []);
                                    $c->setPrimaryKey(['id']);

                        $p = $schema->createTable('performance');
                                    $p->addColumn('id', 'integer', ['autoincrement' => true]);
                                    $p->addColumn('athlete_id', 'integer', []);
                                    $p->addColumn('session_id', 'integer', ['notnull' => false]);
                                    $p->addColumn('discipline', 'string', ['length' => 50]);
                                    $p->addColumn('value', 'decimal', ['precision' => 10, 'scale' => 3]);
                                    $p->addColumn('unit', 'string', ['length' => 20]);
                                    $p->addColumn('notes', 'text', ['notnull' => false]);
                                    $p->addColumn('recorded_at', 'date', []);
                                    $p->addColumn('is_personal_best', 'boolean', ['notnull' => false, 'default' => false]);
                                    $p->addColumn('is_competition', 'boolean', ['notnull' => false, 'default' => false]);
                                    $p->addColumn('is_indoor', 'boolean', ['notnull' => false]);
                                    $p->addColumn('venue', 'string', ['length' => 150, 'notnull' => false]);
                                    $p->addColumn('level', 'string', ['length' => 15, 'notnull' => false]);
                                    $p->addColumn('level_points', 'integer', ['notnull' => false]);
                                    $p->addColumn('wind', 'string', ['length' => 10, 'notnull' => false]);
                                    $p->setPrimaryKey(['id']);

                        $g = $schema->createTable('goal');
                                    $g->addColumn('id', 'integer', ['autoincrement' => true]);
                                    $g->addColumn('athlete_id', 'integer', []);
                                    $g->addColumn('title', 'string', ['length' => 255]);
                                    $g->addColumn('discipline', 'string', ['length' => 50, 'notnull' => false]);
                                    $g->addColumn('target_value', 'decimal', ['precision' => 10, 'scale' => 3, 'notnull' => false]);
                                    $g->addColumn('unit', 'string', ['length' => 20, 'notnull' => false]);
                                    $g->addColumn('deadline', 'date', ['notnull' => false]);
                                    $g->addColumn('status', 'string', ['length' => 20, 'default' => 'in_progress']);
                                    $g->addColumn('notes', 'text', ['notnull' => false]);
                                    $g->addColumn('created_at', 'datetime', []);
                                    $g->setPrimaryKey(['id']);

                        $as = $schema->createTable('athlete_session');
                                    $as->addColumn('id', 'integer', ['autoincrement' => true]);
                                    $as->addColumn('athlete_id', 'integer', []);
                                    $as->addColumn('session_id', 'integer', []);
                                    $as->addColumn('comment', 'text', ['notnull' => false]);
                                    $as->addColumn('difficulty', 'integer', ['notnull' => false]);
                                    $as->addColumn('logged_at', 'datetime', []);
                                    $as->setPrimaryKey(['id']);

                        $av = $schema->createTable('athlete_video');
                                    $av->addColumn('id', 'integer', ['autoincrement' => true]);
                                    $av->addColumn('athlete_id', 'integer', []);
                                    $av->addColumn('title', 'string', ['length' => 255]);
                                    $av->addColumn('discipline', 'string', ['length' => 100, 'notnull' => false]);
                                    $av->addColumn('filename', 'string', ['length' => 255]);
                                    $av->addColumn('created_at', 'datetime', []);
                                    $av->setPrimaryKey(['id']);

                        $cr = $schema->createTable('competition_registration');
                                    $cr->addColumn('id', 'integer', ['autoincrement' => true]);
                                    $cr->addColumn('athlete_id', 'integer', []);
                                    $cr->addColumn('competition_id', 'integer', []);
                                    $cr->addColumn('disciplines', 'json', []);
                                    $cr->addColumn('registered_at', 'datetime', []);
                                    $cr->setPrimaryKey(['id']);

                        $u = $schema->createTable('user');
                                    $u->addColumn('id', 'integer', ['autoincrement' => true]);
                                    $u->addColumn('email', 'string', ['length' => 180]);
                                    $u->addColumn('first_name', 'string', ['length' => 100]);
                                    $u->addColumn('last_name', 'string', ['length' => 100]);
                                    $u->addColumn('roles', 'json', []);
                                    $u->addColumn('password', 'text', []);
                                    $u->addColumn('linked_athlete_id', 'integer', ['notnull' => false]);
                                    $u->setPrimaryKey(['id']);
                                    $u->addUniqueIndex(['email'], 'UNIQ_USER_EMAIL');
                    }
        }

    public function down(Schema $schema): void
        {
                    if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                                    $this->addSql('DROP TABLE IF EXISTS "user"');
                                    $this->addSql('DROP TABLE IF EXISTS competition_registration');
                                    $this->addSql('DROP TABLE IF EXISTS athlete_video');
                                    $this->addSql('DROP TABLE IF EXISTS athlete_session');
                                    $this->addSql('DROP TABLE IF EXISTS goal');
                                    $this->addSql('DROP TABLE IF EXISTS performance');
                                    $this->addSql('DROP TABLE IF EXISTS competition');
                                    $this->addSql('DROP TABLE IF EXISTS session');
                                    $this->addSql('DROP TABLE IF EXISTS athlete');
                                    $this->addSql('DROP TABLE IF EXISTS competition_type');
                                    $this->addSql('DROP TABLE IF EXISTS training_type');
                    } else {
                                    $schema->dropTable('user');
                                    $schema->dropTable('competition_registration');
                                    $schema->dropTable('athlete_video');
                                    $schema->dropTable('athlete_session');
                                    $schema->dropTable('goal');
                                    $schema->dropTable('performance');
                                    $schema->dropTable('competition');
                                    $schema->dropTable('session');
                                    $schema->dropTable('athlete');
                                    $schema->dropTable('competition_type');
                                    $schema->dropTable('training_type');
                    }
        }
    }
