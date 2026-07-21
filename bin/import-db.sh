#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

# Naimportuje staženou zálohu produkční DB do LOKÁLNÍ Docker DB.
#
# Postup: dropne aktuální lokální DB `gamecon`, znovu ji vytvoří a naimportuje
# do ní soubor export_latest.sql (viz bin/stahni-zalohu-db.sh).
#
# Import běží přes mariadb klienta v kontejneru `sql.gamecon`, dump se do něj
# streamuje ze stdin, takže nemusí být uvnitř kontejneru.
#
# Použití:
#   bin/import-db.sh [cesta-k-souboru.sql]
#
# Bez argumentu bere dumps/export_latest.sql (default stahovacího skriptu).

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$DIR")"

# Odpovídá docker-compose.yml (služba web, env DB_*)
DB_SERVICE="sql.gamecon"
DB_NAME="gamecon"

SQL_PATH="${1:-$PROJECT_ROOT/dumps/export_latest.sql}"

if [ ! -f "$SQL_PATH" ]; then
    echo "✗ Soubor neexistuje: $SQL_PATH" >&2
    echo "  Nejdřív stáhni zálohu: bin/stahni-zalohu-db.sh" >&2
    exit 1
fi

# Bez -f, aby se uplatnil i případný docker-compose.override.yml (Compose
# si default soubory najde sám podle --project-directory).
compose() {
    docker compose --project-directory "$PROJECT_ROOT" "$@"
}

# Klient v kontejneru (root/root dle compose konfigurace)
mariadb() {
    compose exec -T "$DB_SERVICE" mariadb -h127.0.0.1 -uroot -proot "$@"
}

echo "→ Zajišťuji běžící kontejner $DB_SERVICE"
compose up -d "$DB_SERVICE"

echo "→ Dropuji a znovu vytvářím DB \`$DB_NAME\`"
mariadb -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

echo "→ Importuji $SQL_PATH do \`$DB_NAME\` (může chvíli trvat)"
# Dump z produkce může obsahovat vlastní CREATE DATABASE / USE / DROP DATABASE
# odkazující na produkční jméno DB — odstraníme je, aby import spolehlivě
# skončil v naší lokální `gamecon`, do které klienta explicitně přepneme.
grep -vE '^(CREATE DATABASE|USE |DROP DATABASE)' "$SQL_PATH" | mariadb "$DB_NAME"

echo "✓ Hotovo: záloha naimportována do lokální DB \`$DB_NAME\`"
