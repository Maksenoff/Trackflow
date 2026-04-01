<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Correctif : crée les tables manquantes sur PostgreSQL (user, etc.)
   * si elles n'existent pas encore. Idempotent grâce à IF NOT EXISTS.
   */
final class Version20260401000001 extends AbstractMigration
  {
        public function getDescription(): string
    {
              return 'Correctif PostgreSQL : création des tables manquantes (IF NOT EXISTS)';
    }

    public function up(Schema $schema): void
    {
              if (!($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform)) {
                            return;
              }

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
    }

    public function down(Schema $schema): void
    {
              if (!($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform)) {
                            return;
              }

            $this->addSql('DROP TABLE IF EXISTS "user" CASCADE');
              $this->addSql('DROP TABLE IF EXISTS competition_registration CASCADE');
              $this->addSql('DROP TABLE IF EXISTS athlete_video CASCADE');
              $this->addSql('DROP TABLE IF EXISTS athlete_session CASCADE');
              $this->addSql('DROP TABLE IF EXISTS goal CASCADE');
              $this->addSql('DROP TABLE IF EXISTS performance CASCADE');
              $this->addSql('DROP TABLE IF EXISTS competition CASCADE');
              $this->addSql('DROP TABLE IF EXISTS session CASCADE');
              $this->addSql('DROP TABLE IF EXISTS athlete CASCADE');
              $this->addSql('DROP TABLE IF EXISTS competition_type CASCADE');
              $this->addSql('DROP TABLE IF EXISTS training_type CASCADE');
    }
  }
