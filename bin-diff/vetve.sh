#!/usr/bin/env bash

# Kde leží porovnávané worktree. Sourcuje se z ostatních skriptů v bin-diff/.
#
# Nová větev se odvodí z umístění tohohle souboru, takže funguje v každém worktree bez
# nastavování. Legacy se hledá vedle něj jako `gamecon-legacy-reference`; když se jmenuje
# jinak nebo leží jinde, dá se obojí přebít proměnnou prostředí:
#
#   GAMECON_DIFF_LEGACY=~/jinde/legacy bin-diff/porovnej.sh

NOVY="${GAMECON_DIFF_NOVY:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
LEGACY="${GAMECON_DIFF_LEGACY:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)/gamecon-legacy-reference}"

for _kde in "$NOVY:GAMECON_DIFF_NOVY" "$LEGACY:GAMECON_DIFF_LEGACY"; do
    _dir="${_kde%%:*}"
    if [ ! -f "$_dir/docker-compose.yml" ]; then
        echo "✗ '$_dir' není worktree gameconu (chybí docker-compose.yml)." >&2
        echo "  Přebij cestu přes ${_kde##*:}=<cesta>." >&2
        exit 1
    fi
done
unset _kde _dir

# Obě větve musí běžet v oddělených compose projektech, jinak `--project-directory`
# sáhne na tentýž kontejner a porovnání mlčky srovnává DB samu se sebou.
if [ "$NOVY" = "$LEGACY" ]; then
    echo "✗ Nová i legacy větev ukazují na '$NOVY' — není co porovnávat." >&2
    exit 1
fi

# Heslo si necháme říct od compose, ne z prostředí: nastavuje se v `.env` toho kterého
# worktree, což se do volajícího shellu nepropíše, a každá větev může mít vlastní. Bez
# toho by se skripty ptaly s „root“ i tam, kde je heslo jiné, a hlásily by jen, že se
# nejde zeptat na databáze.
rootHeslo() {
    local dir="$1" heslo
    heslo=$(docker compose --project-directory "$dir" config 2>/dev/null \
        | sed -n 's/^[[:space:]]*MYSQL_ROOT_PASSWORD:[[:space:]]*//p' | head -1)
    # Compose hodnotu uvozovkuje, kdykoli by se dala přečíst jinak než jako řetězec
    # (typicky číselné heslo). Bez odstranění uvozovek by se posílaly jako součást hesla.
    heslo="${heslo%\"}"; heslo="${heslo#\"}"
    heslo="${heslo%\'}"; heslo="${heslo#\'}"
    printf '%s' "${heslo:-root}"
}
