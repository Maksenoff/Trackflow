<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Schéma initial — compatible SQLite (dev) et PostgreSQL (prod).
 * Utilise l'API Schema de Doctrine pour une génération DDL cross-platform.
 *
 * Si les tables existent déjà (base locale déjà en place), la migration
 * détecte le cas et ne touche à rien, tout en étant marquée comme exécutée.
 */
final class Version20260330000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial TrackFlow (SQLite + PostgreSQL)';
    }

    public function up(Schema $schema): void
    {
        // Si les tables existent déjà (env local SQLite), on ne fait rien.
        if ($schema->hasTable('athlete')) {
            return;
        }

        // ── training_type ─────────────────────────────────────────
        $tt = $schema->createTable('training_type');
        $tt->addColumn('id',    'integer', ['autoincrement' => true]);
        $tt->addColumn('name',  'string',  ['length' => 80]);
        $tt->addColumn('color', 'string',  ['length' => 7, 'default' => '#6366f1']);
        $tt->setPrimaryKey(['id']);

        // ── competition_type ──────────────────────────────────────
        $ct = $schema->createTable('competition_type');
        $ct->addColumn('id',    'integer', ['autoincrement' => true]);
        $ct->addColumn('name',  'string',  ['length' => 80]);
        $ct->addColumn('color', 'string',  ['length' => 7, 'default' => '#f59e0b']);
        $ct->setPrimaryKey(['id']);

        // ── athlete ───────────────────────────────────────────────
        $a = $schema->createTable('athlete');
        $a->addColumn('id',              'integer',  ['autoincrement' => true]);
        $a->addColumn('first_name',      'string',   ['length' => 100]);
        $a->addColumn('last_name',       'string',   ['length' => 100]);
        $a->addColumn('birth_date',      'date',     ['notnull' => false]);
        $a->addColumn('gender',          'string',   ['length' => 20, 'notnull' => false]);
        $a->addColumn('discipline',      'json',     []);
        $a->addColumn('notes',           'text',     ['notnull' => false]);
        $a->addColumn('photo',           'string',   ['length' => 255, 'notnull' => false]);
        $a->addColumn('ffa_profile_url', 'string',   ['length' => 500, 'notnull' => false]);
        $a->addColumn('last_synced_at',  'datetime', ['notnull' => false]);
        $a->addColumn('created_at',      'datetime', []);
        $a->setPrimaryKey(['id']);

        // ── session ───────────────────────────────────────────────
        $s = $schema->createTable('session');
        $s->addColumn('id',               'integer', ['autoincrement' => true]);
        $s->addColumn('title',            'string',  ['length' => 150]);
        $s->addColumn('date',             'date',    []);
        $s->addColumn('description',      'text',    ['notnull' => false]);
        $s->addColumn('duration_minutes', 'integer', ['notnull' => false]);
        $s->addColumn('training_type_id', 'integer', ['notnull' => false]);
        $s->setPrimaryKey(['id']);
        $s->addIndex(['training_type_id'], 'IDX_SESSION_TRAINING_TYPE');
        $s->addForeignKeyConstraint('training_type', ['training_type_id'], ['id'], ['onDelete' => 'SET NULL']);

        // ── competition ───────────────────────────────────────────
        $c = $schema->createTable('competition');
        $c->addColumn('id',                  'integer',  ['autoincrement' => true]);
        $c->addColumn('title',               'string',   ['length' => 150]);
        $c->addColumn('location',            'string',   ['length' => 200, 'notnull' => false]);
        $c->addColumn('date',                'date',     []);
        $c->addColumn('competition_type_id', 'integer',  ['notnull' => false]);
        $c->addColumn('document_filename',   'string',   ['length' => 255, 'notnull' => false]);
        $c->addColumn('website_url',         'string',   ['length' => 500, 'notnull' => false]);
        $c->addColumn('description',         'text',     ['notnull' => false]);
        $c->addColumn('created_at',          'datetime', []);
        $c->setPrimaryKey(['id']);
        $c->addIndex(['competition_type_id'], 'IDX_COMPETITION_TYPE');
        $c->addForeignKeyConstraint('competition_type', ['competition_type_id'], ['id'], ['onDelete' => 'SET NULL']);

        // ── performance ───────────────────────────────────────────
        $p = $schema->createTable('performance');
        $p->addColumn('id',               'integer', ['autoincrement' => true]);
        $p->addColumn('athlete_id',       'integer', []);
        $p->addColumn('session_id',       'integer', ['notnull' => false]);
        $p->addColumn('discipline',       'string',  ['length' => 50]);
        $p->addColumn('value',            'decimal', ['precision' => 10, 'scale' => 3]);
        $p->addColumn('unit',             'string',  ['length' => 20]);
        $p->addColumn('notes',            'text',    ['notnull' => false]);
        $p->addColumn('recorded_at',      'date',    []);
        $p->addColumn('is_personal_best', 'boolean', ['notnull' => false, 'default' => false]);
        $p->addColumn('is_competition',   'boolean', ['notnull' => false, 'default' => false]);
        $p->addColumn('is_indoor',        'boolean', ['notnull' => false]);
        $p->addColumn('venue',            'string',  ['length' => 150, 'notnull' => false]);
        $p->addColumn('level',            'string',  ['length' => 15, 'notnull' => false]);
        $p->addColumn('level_points',     'integer', ['notnull' => false]);
        $p->addColumn('wind',             'string',  ['length' => 10, 'notnull' => false]);
        $p->setPrimaryKey(['id']);
        $p->addIndex(['athlete_id'], 'IDX_PERF_ATHLETE');
        $p->addIndex(['session_id'], 'IDX_PERF_SESSION');
        $p->addForeignKeyConstraint('athlete',  ['athlete_id'], ['id'], ['onDelete' => 'CASCADE']);
        $p->addForeignKeyConstraint('session',  ['session_id'], ['id'], ['onDelete' => 'SET NULL']);

        // ── goal ──────────────────────────────────────────────────
        $g = $schema->createTable('goal');
        $g->addColumn('id',           'integer', ['autoincrement' => true]);
        $g->addColumn('athlete_id',   'integer', []);
        $g->addColumn('title',        'string',  ['length' => 255]);
        $g->addColumn('discipline',   'string',  ['length' => 50, 'notnull' => false]);
        $g->addColumn('target_value', 'decimal', ['precision' => 10, 'scale' => 3, 'notnull' => false]);
        $g->addColumn('unit',         'string',  ['length' => 20, 'notnull' => false]);
        $g->addColumn('deadline',     'date',    ['notnull' => false]);
        $g->addColumn('status',       'string',  ['length' => 20, 'default' => 'in_progress']);
        $g->addColumn('notes',        'text',    ['notnull' => false]);
        $g->addColumn('created_at',   'datetime', []);
        $g->setPrimaryKey(['id']);
        $g->addIndex(['athlete_id'], 'IDX_GOAL_ATHLETE');
        $g->addForeignKeyConstraint('athlete', ['athlete_id'], ['id'], ['onDelete' => 'CASCADE']);

        // ── athlete_session ───────────────────────────────────────
        $as = $schema->createTable('athlete_session');
        $as->addColumn('id',         'integer', ['autoincrement' => true]);
        $as->addColumn('athlete_id', 'integer', []);
        $as->addColumn('session_id', 'integer', []);
        $as->addColumn('comment',    'text',    ['notnull' => false]);
        $as->addColumn('difficulty', 'integer', ['notnull' => false]);
        $as->addColumn('logged_at',  'datetime', []);
        $as->setPrimaryKey(['id']);
        $as->addUniqueIndex(['athlete_id', 'session_id'], 'athlete_session_unique');
        $as->addIndex(['athlete_id'], 'IDX_AS_ATHLETE');
        $as->addIndex(['session_id'], 'IDX_AS_SESSION');
        $as->addForeignKeyConstraint('athlete',  ['athlete_id'], ['id'], ['onDelete' => 'CASCADE']);
        $as->addForeignKeyConstraint('session',  ['session_id'], ['id'], ['onDelete' => 'CASCADE']);

        // ── athlete_video ─────────────────────────────────────────
        $av = $schema->createTable('athlete_video');
        $av->addColumn('id',          'integer',  ['autoincrement' => true]);
        $av->addColumn('athlete_id',  'integer',  []);
        $av->addColumn('title',       'string',   ['length' => 255]);
        $av->addColumn('discipline',  'string',   ['length' => 100, 'notnull' => false]);
        $av->addColumn('filename',    'string',   ['length' => 255]);
        $av->addColumn('created_at',  'datetime', []);
        $av->setPrimaryKey(['id']);
        $av->addIndex(['athlete_id'], 'IDX_AV_ATHLETE');
        $av->addForeignKeyConstraint('athlete', ['athlete_id'], ['id'], ['onDelete' => 'CASCADE']);

        // ── competition_registration ──────────────────────────────
        $cr = $schema->createTable('competition_registration');
        $cr->addColumn('id',             'integer',  ['autoincrement' => true]);
        $cr->addColumn('athlete_id',     'integer',  []);
        $cr->addColumn('competition_id', 'integer',  []);
        $cr->addColumn('disciplines',    'json',     []);
        $cr->addColumn('registered_at',  'datetime', []);
        $cr->setPrimaryKey(['id']);
        $cr->addUniqueIndex(['athlete_id', 'competition_id'], 'uq_athlete_competition');
        $cr->addIndex(['athlete_id'],     'IDX_CR_ATHLETE');
        $cr->addIndex(['competition_id'], 'IDX_CR_COMPETITION');
        $cr->addForeignKeyConstraint('athlete',     ['athlete_id'],     ['id'], ['onDelete' => 'CASCADE']);
        $cr->addForeignKeyConstraint('competition', ['competition_id'], ['id'], ['onDelete' => 'CASCADE']);

        // ── user ──────────────────────────────────────────────────
        // NB : "user" est un mot réservé SQL → Doctrine le quote automatiquement
        // en fonction de la plateforme (" pour PostgreSQL, ` pour MySQL).
        $u = $schema->createTable('user');
        $u->addColumn('id',                 'integer', ['autoincrement' => true]);
        $u->addColumn('email',              'string',  ['length' => 180]);
        $u->addColumn('first_name',         'string',  ['length' => 100]);
        $u->addColumn('last_name',          'string',  ['length' => 100]);
        $u->addColumn('roles',              'json',    []);
        $u->addColumn('password',           'text',    []);
        $u->addColumn('linked_athlete_id',  'integer', ['notnull' => false]);
        $u->setPrimaryKey(['id']);
        $u->addUniqueIndex(['email'], 'UNIQ_USER_EMAIL');
        $u->addIndex(['linked_athlete_id'], 'IDX_USER_ATHLETE');
        $u->addForeignKeyConstraint('athlete', ['linked_athlete_id'], ['id'], ['onDelete' => 'SET NULL']);
    }

    public function down(Schema $schema): void
    {
        // Suppression dans l'ordre inverse des dépendances FK
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
