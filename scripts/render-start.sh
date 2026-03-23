#!/usr/bin/env sh
set -eu

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache

php artisan optimize:clear
php artisan storage:link || true

attempt=1
max_attempts=10
until php artisan migrate --force; do
  if [ "$attempt" -ge "$max_attempts" ]; then
    echo "Migrations failed after ${max_attempts} attempts."
    exit 1
  fi

  echo "Migration attempt ${attempt} failed. Retrying in 5 seconds..."
  attempt=$((attempt + 1))
  sleep 5
done

if [ "${AUTO_SEED_STARTER_ACCOUNTS:-false}" = "true" ]; then
  php artisan db:seed --class='Database\\Seeders\\StarterAccountsSeeder' --force
fi

exec php -S "0.0.0.0:${PORT:-10000}" -t public public/router.php
