#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

# Testy s pojistkou proti posunutému času.
#
# `GAMECON_DIFF_DATUM` jde do PHP přes auto_prepend_file, takže platí pro KAŽDÝ běh PHP
# v kontejneru — testy včetně. `DateTimeGameconTest` a `SystemoveNastaveniTest` počítají
# přesně s těmi konstantami a s posunem spadnou. Vypadá to jako rozbitá větev, a není.
#
#   bin-diff/testy.sh                    celá sada
#   bin-diff/testy.sh symfony/tests/     jen část

KOREN="$(cd "$(dirname "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)")" && pwd)"

# Ptáme se běžícího kontejneru, ne `.env`. PHP dostane hodnotu zapečenou při recreate,
# takže když `cas.sh --vrat` uklidil `.env`, ale recreate kontejneru selhal, je soubor
# čistý a testy přesto běží na posunutém čase — přesně ten případ, kvůli kterému tahle
# pojistka existuje. Kontejner nemusí běžet; pak rozhoduje `.env`.
datum=$(docker compose --project-directory "$KOREN" exec -T web printenv GAMECON_DIFF_DATUM 2>/dev/null | tr -d '\r') || true
if [ -z "$datum" ] && grep -q '^GAMECON_DIFF_DATUM=' "$KOREN/.env" 2>/dev/null; then
    datum=$(grep '^GAMECON_DIFF_DATUM=' "$KOREN/.env" | cut -d= -f2)
fi

if [ -n "$datum" ]; then
    echo "✗ Je nastavený posunutý čas ($datum) — testy by spadly na datových výpočtech." >&2
    echo "  Nejdřív: bin-diff/cas.sh --vrat" >&2
    exit 1
fi

exec "$KOREN/bin/phpunit.sh" "$@"
