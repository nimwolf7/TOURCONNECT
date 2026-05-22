#!/usr/bin/env bash
set -euo pipefail

export APP_ENV="${APP_ENV:-prod}"
export APP_DEBUG="${APP_DEBUG:-0}"

# JWT keys are not committed; generate during Railway build.
if [ ! -f config/jwt/private.pem ] || [ ! -f config/jwt/public.pem ]; then
  php bin/console lexik:jwt:generate-keypair --no-interaction
fi

# Warm cache when possible (DATABASE_URL should be linked from MySQL service).
php bin/console cache:clear --no-warmup --env="$APP_ENV" || true
php bin/console cache:warmup --env="$APP_ENV" || true
