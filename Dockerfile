FROM dunglas/frankenphp

WORKDIR /app

# Install PHP extensions
RUN apt-get update \
 && apt-get install -y libpq-dev \
 && docker-php-ext-install pdo_pgsql pgsql \
 && docker-php-ext-enable pdo_pgsql pgsql \
 && rm -rf /var/lib/apt/lists/*

# Install Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
 && apt-get install -y nodejs \
 && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Fake env for build (important)
ENV APP_ENV=prod
ENV APP_SECRET=buildsecret
ENV DATABASE_URL="postgresql://dummy:dummy@127.0.0.1:5432/dummy?serverVersion=16"

# Install Symfony assets
RUN php bin/console importmap:install

# Install Node dependencies
RUN npm ci

# Tailwind binary
RUN mkdir -p var/tailwind \
 && curl -sLo var/tailwind/tailwindcss https://github.com/tailwindlabs/tailwindcss/releases/latest/download/tailwindcss-linux-x64 \
 && chmod +x var/tailwind/tailwindcss

# Build assets
RUN php bin/console tailwind:build --minify
RUN php bin/console asset-map:compile 2>/dev/null || true

# Permissions
RUN mkdir -p var/cache var/log && chmod -R 777 var

# 👉 IMPORTANT : FrankenPHP config
COPY Caddyfile /etc/caddy/Caddyfile

# Entry script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8080

ENV SERVER_NAME=:8080

ENTRYPOINT ["docker-entrypoint.sh"]
