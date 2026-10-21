#!/bin/sh
set -e

composer install --no-interaction --prefer-dist

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64' .env; then
    php artisan key:generate --force
fi

php artisan migrate --force

# Seed only on an empty database, the seeders are not idempotent
if php -r '
    $pdo = new PDO("mysql:host=" . getenv("DB_HOST") . ";port=" . getenv("DB_PORT") . ";dbname=" . getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));
    exit((int) $pdo->query("SELECT COUNT(*) FROM workshops")->fetchColumn() > 0 ? 1 : 0);
'; then
    php artisan db:seed --force
fi

exec "$@"
