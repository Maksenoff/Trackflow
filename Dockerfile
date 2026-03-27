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

  # Install PHP dependencies
  RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

  # Install Node dependencies
  RUN npm ci

  # Pre-download Tailwind CSS binary for linux/amd64 (used by symfonycasts/tailwind-bundle)
  RUN mkdir -p var/tailwind \
   && curl -sLo var/tailwind/tailwindcss https://github.com/tailwindlabs/tailwindcss/releases/latest/download/tailwindcss-linux-x64 \
    && chmod +x var/tailwind/tailwindcss

    # Build Tailwind CSS with dummy env vars so Symfony can bootstrap at build time
    RUN APP_ENV=prod APP_SECRET=buildsecret DATABASE_URL="postgresql://u:p@localhost/db" php bin/console tailwind:build --minify

    # Compile asset map
    RUN APP_ENV=prod APP_SECRET=buildsecret DATABASE_URL="postgresql://u:p@localhost/db" php bin/console asset-map:compile 2>/dev/null || true

    # Ensure var directory exists with correct permissions
    RUN mkdir -p var/cache var/log && chmod -R 777 var

    EXPOSE 8080

    CMD ["frankenphp", "php-server", "--root", "/app/public"]
