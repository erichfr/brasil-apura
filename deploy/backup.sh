#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
dc() { docker compose --env-file .env.production -f compose.production.yaml "$@"; }
umask 077
destination="backups/$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$destination"
scheduler_running="$(dc ps --status running --services scheduler)"
if [[ -n "$scheduler_running" ]]; then
    dc stop scheduler
    trap 'dc start scheduler >/dev/null' EXIT
fi
dc exec -T db sh -c 'export MYSQL_PWD="$MARIADB_ROOT_PASSWORD"; exec mariadb-dump --single-transaction --quick --routines --triggers --user=root "$MARIADB_DATABASE"' | gzip > "$destination/database.sql.gz"
dc run --rm -T --no-deps --entrypoint tar app -czf - -C /var/www/html/storage app/private > "$destination/raw-sources.tar.gz"
cp -p .env.production "$destination/environment"
git rev-parse HEAD > "$destination/commit.txt"
echo "Backup local criado em $destination. Copie para um local privado fora da VM."
