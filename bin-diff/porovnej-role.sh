#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

# Pro KAŽDÉHO uživatele s rolí porovná významy rolí nové vrstvy s legacy pohledem a ověří, že
# se jeho role dají načíst jako entity. Proč, nad čím to pouštět a co znamenají kódy výstupu:
# docs/generated/eshop-diferencni-overeni.md, část „Kontrola přes všechny uživatele“.
#
#   bin-diff/porovnej-role.sh

NOVY="${GAMECON_DIFF_NOVY:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
if [ ! -f "$NOVY/docker-compose.yml" ]; then
    echo "✗ '$NOVY' není worktree gameconu (chybí docker-compose.yml)." >&2
    echo "  Přebij cestu přes GAMECON_DIFF_NOVY=<cesta>." >&2
    exit 1
fi

SKRIPT="$(dirname "${BASH_SOURCE[0]}")/role-vyznamy.php"
KOPIE="$NOVY/symfony/var/role-vyznamy.php"

# Kopie skriptu musí zmizet i když běh spadne — jinak zůstane ležet ve worktree.
trap 'rm -f "$KOPIE"' EXIT
cp "$SKRIPT" "$KOPIE"

# Bez `cd` by `bin-docker/php` sáhl na compose projekt aktuálního adresáře, ne na ten nové větve.
cd "$NOVY"
stav=0
# Vývojové jádro si pamatuje každý dotaz, takže s tisíci uživateli by 128 M nestačilo.
vystup=$(./bin-docker/php -d memory_limit=1G symfony/var/role-vyznamy.php) || stav=$?
printf '%s\n' "$vystup"
if [ "$stav" -ne 0 ]; then
    exit "$stav"
fi

# Zavaděč při nedokončené migraci vypíše stránku a skončí s 0, kód výstupu tedy nestačí;
# úspěch je jen závěrečná věta skriptu.
if ! grep -q '^✓ Nová vrstva čte role' <<<"$vystup"; then
    echo "✗ Skript nedoběhl do konce (jsou v databázi dokončené migrace? ./bin-docker/php bin/console migrations:continue)." >&2
    exit 3
fi
