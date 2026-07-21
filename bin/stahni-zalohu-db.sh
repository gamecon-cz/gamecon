#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

# Stáhne poslední zálohu produkční DB z gamecon.cz, rozbalí ji a odstraní DEFINER.
#
# Výsledek: <cíl>/export_latest.sql.gz (rozbalený, bez DEFINER=`user`@`host`)
#
# Použití:
#   bin/stahni-zalohu-db.sh [cílový-adresář]
#
# Bez argumentu se stahuje do git-ignorovaného adresáře dumps/ v kořeni projektu.

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$DIR")"

REMOTE_HOST="gamecon.cz"
REMOTE_DIR="/srv/ftp/gamecon.cz/www/gamecon.cz/ostra/backup/db"
REMOTE_FILE="export_latest.sql.gz"

TARGET_DIR="${1:-$PROJECT_ROOT/dumps}"

mkdir -p "$TARGET_DIR"

GZ_PATH="$TARGET_DIR/$REMOTE_FILE"
# gunzip odvozuje jméno rozbaleného souboru odstraněním přípony .gz
SQL_PATH="$TARGET_DIR/${REMOTE_FILE%.gz}"

echo "→ Stahuji $REMOTE_HOST:$REMOTE_DIR/$REMOTE_FILE"
scp "$REMOTE_HOST:$REMOTE_DIR/$REMOTE_FILE" "$GZ_PATH"

echo "→ Rozbaluji do $SQL_PATH"
gunzip -f -k "$GZ_PATH"

echo "→ Odstraňuji DEFINER z $SQL_PATH"
sed -E -i 's/DEFINER=`[^`]+`@`[^`]+`//' "$SQL_PATH"

echo "✓ Hotovo: $SQL_PATH"
