FROM dunglas/frankenphp

WORKDIR /app

# Install PostgreSQL library and PHP pdo_pgsql extension
RUN install-php-extensions pdo_pgsql opcache intl zip

# Install Node.js and npm
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
 && apt-get install -y nodejs \
  && rm -rf /var/lib/apt/lists/*

  # Install Composer
  COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

  # Copy application files
  COPY . .

  # Copy Caddyfile to the standard location FrankenPHP reads by default
  COPY Caddyfile /etc/caddy/Caddyfile

  # Install PHP dependencies (no scripts to avoid cache:clear at build time)
  RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

  # Install Node dependencies and build CSS
  RUN npm ci && php bin/console tailwind:build --minify 2>/dev/null || true

  # Compile asset map
  RUN php bin/console asset-map:compile 2>/dev/null || true

  # Ensure var directory exists with correct permissions
  RUN mkdir -p var/cache var/log && chmod -R 777 var

  EXPOSE 8080

  CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
