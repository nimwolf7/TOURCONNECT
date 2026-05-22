#!/usr/bin/env bash
set -uo pipefail

export APP_ENV="${APP_ENV:-prod}"
export APP_DEBUG="${APP_DEBUG:-0}"
PORT="${PORT:-8080}"

if [ ! -f config/jwt/private.pem ] || [ ! -f config/jwt/public.pem ]; then
  php bin/console lexik:jwt:generate-keypair --no-interaction 2>/dev/null || true
fi

php bin/console cache:clear --no-warmup --env="$APP_ENV" 2>/dev/null || true
php bin/console cache:warmup --env="$APP_ENV" 2>/dev/null || true

echo "Starting Symfony on 0.0.0.0:${PORT}"
exec php -S "0.0.0.0:${PORT}" -t public public/index.php
