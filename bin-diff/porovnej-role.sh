#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

# Pro KAŽDÉHO uživatele s nějakou rolí porovná významy rolí, které čte nová vrstva
# (`UserRoleRepository`), s tím, co čte legacy (pohled `platne_role_uzivatelu`), a ověří, že
# se jeho role dají načíst jako entity. Vzorek několika účtů v scénářích tohle nezachytí:
# role minulých ročníků mívá jen část lidí a neznámý význam v záloze shodí jen jejich požadavky.
#
# Stačí nová větev (legacy tu nemá co porovnávat — referencí je sám pohled v databázi), ale
# běh musí být nad zálohou produkce, jinak v databázi nejsou role minulých ročníků.
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

# Zavaděč při nedokončené migraci vypíše stránku s tlačítkem a skončí s 0, takže kód
# výstupu sám nic neříká. Úspěch je jen ta závěrečná věta, kterou skript vypíše na konci.
if ! grep -q '^✓ Nová vrstva čte role' <<<"$vystup"; then
    echo "✗ Skript nedoběhl do konce (jsou v databázi dokončené migrace? ./bin-docker/php bin/console migrations:continue)." >&2
    exit 3
fi
