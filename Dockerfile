FROM dunglas/frankenphp

WORKDIR /app

# Install PHP extensions
RUN install-php-extensions pdo_pgsql opcache intl zip

# Install Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
 && apt-get install -y nodejs \
 && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . .

# Install PHP dependencies (no scripts pour éviter accès DB)
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# 👉 FAKE DATABASE_URL pour le build uniquement
ENV APP_ENV=prod
ENV APP_SECRET=buildsecret
ENV DATABASE_URL="postgresql://dummy:dummy@127.0.0.1:5432/dummy?serverVersion=16"

# Install importmap assets
RUN php bin/console importmap:install

# Install Node dependencies
RUN npm ci

# Pre-download Tailwind CSS
RUN mkdir -p var/tailwind \
 && curl -sLo var/tailwind/tailwindcss https://github.com/tailwindlabs/tailwindcss/releases/latest/download/tailwindcss-linux-x64 \
 && chmod +x var/tailwind/tailwindcss

# Build Tailwind CSS
RUN php bin/console tailwind:build --minify

# Compile asset map
RUN php bin/console asset-map:compile 2>/dev/null || true

# Permissions
RUN mkdir -p var/cache var/log && chmod -R 777 var

# Entrypoint
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["docker-entrypoint.sh"]
