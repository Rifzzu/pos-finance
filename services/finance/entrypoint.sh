#!/bin/sh
set -e
PORT="${PORT:-8003}"
echo "[finance] waiting for DB ${DB_HOST}:${DB_PORT}..."
for i in $(seq 1 60); do
  if php -r "try { new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); exit(0); } catch (Throwable $e) { exit(1); }" 2>/dev/null; then
    echo "[finance] DB ready"
    break
  fi
  sleep 1
  if [ "$i" -eq 60 ]; then echo "[finance] DB not ready, continuing..."; fi
done
php artisan migrate --force
if [ "$RUN_SEEDERS" = "true" ]; then
  php artisan db:seed --force || true
fi
php artisan config:cache || true
echo "[finance] serving on :$PORT"
exec php artisan serve --host=0.0.0.0 --port="$PORT"
