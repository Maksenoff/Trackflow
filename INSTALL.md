# Trackflow — Installation sur un nouveau PC

## Prérequis

- PHP 8.2+ (avec extensions : pdo, pdo_sqlite, intl, mbstring, xml, curl)
- Composer
- Node.js + npm
- Symfony CLI (`scoop install symfony-cli` ou https://symfony.com/download)
- Git

## Installation

```bash
# 1. Cloner le repo (BDD incluse)
git clone https://github.com/Maksenoff/Trackflow.git
cd Trackflow

# 2. Dépendances PHP
composer install

# 3. Dépendances JS + build Tailwind
npm install
npm run build

# 4. Lancer le serveur
symfony server:start
```

Ouvrir : http://localhost:8000

La base de données SQLite (`var/data_dev.db`) est incluse dans le repo — aucune migration à jouer.

## Notes

- `.env` est déjà configuré pour SQLite en mode `dev`
- Les uploads (photos athlètes, vidéos) sont dans `public/uploads/` — également inclus
- Pour relancer Tailwind en watch : `npm run watch` dans un terminal séparé


<!-- Trigger CI deploy -->
