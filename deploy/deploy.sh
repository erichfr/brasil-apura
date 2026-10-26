#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
case "${1:-}" in ""|--sync) ;; *) echo "Uso: bash deploy/deploy.sh [--sync]" >&2; exit 2;; esac
[[ -f .env.production ]] || { echo "Execute deploy/init.sh primeiro." >&2; exit 1; }
dc() { docker compose --env-file .env.production -f compose.production.yaml "$@"; }
dc config --quiet
dc build app
dc stop scheduler
dc up -d --wait --wait-timeout 180 db
dc run --rm -T --no-deps --user www-data app php artisan migrate --force
dc run --rm -T --no-deps --user www-data app php artisan db:seed --force
if [[ "${1:-}" == "--sync" ]]; then
    dc run --rm -T --no-deps --user www-data app php -d max_execution_time=0 artisan tse:sync --all-scopes
    dc run --rm -T --no-deps --user www-data app php -d max_execution_time=0 artisan tse:sync-abroad
fi
dc up -d --wait --wait-timeout 180 app gateway
dc up -d scheduler
dc ps
echo "Aplicacao iniciada. Confira o dominio HTTPS e os logs do coletor."
