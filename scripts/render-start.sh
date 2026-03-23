#!/usr/bin/env sh
set -eu

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache

php artisan storage:link || true

attempt=1
max_attempts=10
until php artisan migrate --force --graceful; do
  if [ "$attempt" -ge "$max_attempts" ]; then
    echo "Migrations failed after ${max_attempts} attempts."
    exit 1
  fi

  echo "Migration attempt ${attempt} failed. Retrying in 5 seconds..."
  attempt=$((attempt + 1))
  sleep 5
done

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
