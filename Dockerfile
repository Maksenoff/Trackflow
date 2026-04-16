FROM dunglas/frankenphp:latest

WORKDIR /app

# 1. Installation groupée des extensions (plus efficace)
RUN apt-get update && apt-get install -y \
    git unzip libpq-dev \
 && install-php-extensions \
    pdo_pgsql \
    pgsql \
    intl \
    zip \
    opcache \
 && rm -rf /var/lib/apt/lists/*

# Install Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
 && apt-get install -y nodejs \
 && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . .

# Fake env for build
ENV APP_ENV=prod
ENV APP_SECRET=buildsecret
ENV DATABASE_URL="postgresql://dummy:dummy@127.0.0.1:5432/dummy?serverVersion=16"

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Assets & Tailwind
RUN php bin/console importmap:install
RUN npm ci
RUN mkdir -p var/tailwind \
 && curl -sLo var/tailwind/tailwindcss https://github.com/tailwindlabs/tailwindcss/releases/latest/download/tailwindcss-linux-x64 \
 && chmod +x var/tailwind/tailwindcss
RUN php bin/console tailwind:build --minify
RUN php bin/console asset-map:compile

RUN php bin/console importmap:install --no-interaction
RUN php bin/console asset-map:compile || true

# Permissions
RUN mkdir -p var/cache var/log && chmod -R 777 var

# Config
COPY Caddyfile /etc/caddy/Caddyfile
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8080
ENV SERVER_NAME=:8080

ENTRYPOINT ["docker-entrypoint.sh"]
