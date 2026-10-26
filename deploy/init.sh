#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
if [[ -e .env.production ]]; then
    echo ".env.production ja existe. A chave e as senhas nao foram alteradas." >&2
    exit 1
fi
host="${1:-}"
if [[ ! "$host" =~ ^[a-zA-Z0-9]([a-zA-Z0-9.-]*[a-zA-Z0-9])?\.[a-zA-Z]{2,}$ ]]; then
    echo "Uso: bash deploy/init.sh painel.seu-dominio.com" >&2
    exit 2
fi
command -v openssl >/dev/null
umask 077
app_key="base64:$(openssl rand -base64 32 | tr -d '\n')"
db_password="$(openssl rand -hex 32)"
root_password="$(openssl rand -hex 32)"
temporary="$(mktemp .env.production.XXXXXX)"
trap 'rm -f -- "$temporary"' EXIT
sed -e "s|^SITE_HOST=.*|SITE_HOST=$host|" \
    -e "s|^APP_URL=.*|APP_URL=https://$host|" \
    -e "s|^APP_KEY=.*|APP_KEY=$app_key|" \
    -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$db_password|" \
    -e "s|^MARIADB_ROOT_PASSWORD=.*|MARIADB_ROOT_PASSWORD=$root_password|" \
    .env.production.example > "$temporary"
mv -- "$temporary" .env.production
chmod 600 .env.production
echo "Configuracao de producao criada. Segredos nao sao exibidos."
