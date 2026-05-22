# Railway must build with Dockerfile (not Nixpacks). See railway.json.
FROM php:8.4.3-cli-bookworm

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN php -v

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    curl \
    ca-certificates \
    gnupg \
    && docker-php-ext-install intl pdo_mysql zip opcache \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/* \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./

RUN php -v && composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts

COPY . .

RUN cp .env.prod .env

ARG JWT_PASSPHRASE=railway-build-passphrase
ENV JWT_PASSPHRASE=${JWT_PASSPHRASE}

RUN mkdir -p public/build var/cache var/log config/jwt \
    && php bin/console lexik:jwt:generate-keypair --no-interaction 2>/dev/null || true \
    && composer dump-autoload --optimize --classmap-authoritative \
    && (npm ci && npm run build || true) \
    && test -f public/build/entrypoints.json || printf '%s' '{"entrypoints":{"app":{"js":[],"css":[]}},"integrity":[]}' > public/build/entrypoints.json \
    && test -f public/build/manifest.json || printf '%s' '{}' > public/build/manifest.json \
    && chmod -R 777 var config/jwt \
    && chmod +x scripts/railway-start.sh scripts/railway-build.sh

ENV APP_ENV=prod
ENV APP_DEBUG=0

EXPOSE 8080

CMD ["bash", "scripts/railway-start.sh"]
