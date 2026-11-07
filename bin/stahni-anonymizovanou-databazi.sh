#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

# Nahradí migrace/pomocne/gc_anonymizovana_databaze.sql, databázi, ze které startuje čerstvý checkout,
# anonymizovanou databází, kterou ostrá každou noc znovu vytvoří.
#
# Spusť to po zredukování nebo smazání migrací a na přelomu roku: dump z roku N přestane fungovat, až ROCNIK
# dosáhne N+2. Hlídá to tests/SystemoveNastaveni/AnonymizovanaDatabazeVRepozitariTest.php.
#
# Použití:
#   bin/stahni-anonymizovanou-databazi.sh

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$DIR")"

REMOTE_HOST="gamecon.cz"
REMOTE_FILE="/srv/www/ostra/backup/db/gc_anonymizovana_databaze.sql.gz"
TARGET="$PROJECT_ROOT/migrace/pomocne/gc_anonymizovana_databaze.sql"

TMP_GZ="$(mktemp)"
TMP_SQL="$(mktemp)"
trap 'rm -f "$TMP_GZ" "$TMP_SQL"' EXIT

echo "→ Stahuji $REMOTE_HOST:$REMOTE_FILE"
scp "$REMOTE_HOST:$REMOTE_FILE" "$TMP_GZ"

echo "→ Rozbaluji do $TARGET"
gunzip -c "$TMP_GZ" > "$TMP_SQL"
install -m 0644 "$TMP_SQL" "$TARGET"

echo "✓ Hotovo. Repozitář je veřejný, takže dump nekommituj, dokud neprojde test:"
echo "  ./bin-docker/docker-bash bin/phpunit.sh tests/SystemoveNastaveni/AnonymizovanaDatabazeVRepozitariTest.php"
