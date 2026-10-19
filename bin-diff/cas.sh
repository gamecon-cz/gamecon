#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

# Nastaví „teď" pro diferenční scénáře na OBOU větvích stejně.
#
# Termíny se neposouvají v databázi. SystemoveNastaveni je publikuje jako PHP konstanty a
# u nenastavených si je dopočítá z ročníku, takže UPDATE v DB legacy větev vůbec neovlivní.
# Místo toho se konstanty nadefinují dřív než aplikace (auto_prepend_file) — definují se pod
# `if (!defined(...))`, takže první definice vyhrává a jádro se upravovat nemusí.
#
#   bin-diff/cas.sh 2026-05-12     chovej se, jako by dnes bylo 2026-05-12
#   bin-diff/cas.sh --vrat         zpět na skutečné termíny
#   bin-diff/cas.sh --ukaz         co je teď nastavené

# shellcheck source=vetve.sh
source "$(dirname "${BASH_SOURCE[0]}")/vetve.sh"

# Produkty mají vlastní nabizet_do přímo v shop_predmety. Samotné konstanty nestačí —
# bez posunu sloupce jsou produkty „po termínu" a e-shop nenabídne nic.
posun_nabizet_do() {
    local datum="$1" dir label
    for label in legacy novy; do
        [ "$label" = legacy ] && dir="$LEGACY" || dir="$NOVY"
        if [ -n "$datum" ]; then
            # Počítá se od dnešní půlnoci, ne od „teď“: `cestovani-casem.php` bere posun
            # jako rozdíl kalendářních dnů, a dělení fixními 86400 vyjde o den jinak,
            # kdykoli mezi dneškem a cílem leží přechod na letní čas. Sloupec
            # `nabizet_do` by se rozešel s konstantami a vypadalo by to jako rozdíl větví.
            local offset
            offset=$(( ( $(date -d 'today 00:00' +%s) - $(date -d "$datum" +%s) ) / 86400 ))
            docker compose --project-directory "$dir" exec -T sql.gamecon \
                mariadb -uroot -p"$(rootHeslo "$dir")" gamecon -e "
                CREATE TABLE IF NOT EXISTS shop_predmety_nabizet_do_zaloha (
                    id_predmetu BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                    nabizet_do DATETIME NULL
                ) ENGINE=InnoDB;
                INSERT IGNORE INTO shop_predmety_nabizet_do_zaloha (id_predmetu, nabizet_do)
                    SELECT id_predmetu, nabizet_do FROM shop_predmety;
                UPDATE shop_predmety p
                    JOIN shop_predmety_nabizet_do_zaloha z ON z.id_predmetu = p.id_predmetu
                    SET p.nabizet_do = DATE_ADD(z.nabizet_do, INTERVAL $offset DAY)
                    WHERE z.nabizet_do > '1000-01-01';" 2>/dev/null
        else
            # Záloha nemusí existovat: `reset.sh` obnoví DB ze snapshotu, ve kterém tabulka
            # ještě nebyla, a tím ji zahodí. Původní `nabizet_do` ale tentýž reset vrátil,
            # takže není co obnovovat. Tolerujeme proto jen chybějící tabulku — ostatní
            # chyby (špatné heslo, ležící kontejner) se musí ozvat, jinak by `--vrat`
            # hlásil úspěch a `nabizet_do` zůstalo posunuté proti konstantám.
            if ! chyba=$(docker compose --project-directory "$dir" exec -T sql.gamecon \
                mariadb -uroot -p"$(rootHeslo "$dir")" gamecon -e "
                UPDATE shop_predmety p
                    JOIN shop_predmety_nabizet_do_zaloha z ON z.id_predmetu = p.id_predmetu
                    SET p.nabizet_do = z.nabizet_do;" 2>&1); then
                if ! grep -q "shop_predmety_nabizet_do_zaloha' doesn't exist" <<<"$chyba"; then
                    echo "✗ '$dir': nepovedlo se vrátit nabizet_do:" >&2
                    sed 's/^/    /' <<<"$chyba" >&2
                    exit 1
                fi
            fi
        fi
    done
}

# Konstanty se do PHP dostanou přes auto_prepend_file, který zapíná tahle .ini. Je
# gitignorovaná (mimo scénář by posouvala čas i testům), takže ji musí založit skript —
# jinak by `cas.sh` na cizím stroji tiše neudělal nic.
zaridIni() {
    local dir="$1" ini
    ini="$dir/.docker/php/conf.d/diff-cestovani.ini"
    [ -f "$ini" ] && return 0
    mkdir -p "$(dirname "$ini")"
    printf 'auto_prepend_file=/var/www/html/gamecon/bin-diff/cestovani-casem.php\n' > "$ini"
}

# Bez namountované .ini a předané proměnné se čas neposune, ale všechno ostatní projde —
# scénář pak běží na skutečném datu a vypadá to jako rozdíl mezi větvemi.
overWiring() {
    local dir="$1"
    # Ptáme se compose na výslednou konfiguraci, ne grepem na soubory: grep by vzal
    # i docker-compose.example.yml, který se nikam neskládá, a check by prošel i tam,
    # kde override chybí — tedy přesně v případě, kvůli kterému existuje.
    if ! docker compose --project-directory "$dir" config 2>/dev/null \
        | grep -q 'GAMECON_DIFF_DATUM'; then
        echo "✗ '$dir': compose nepředává GAMECON_DIFF_DATUM do služby web." >&2
        echo "  Doplň do docker-compose.override.yml (viz docker-compose.example.yml):" >&2
        echo "    volumes:     - ./.docker/php/conf.d/diff-cestovani.ini:/usr/local/etc/php/conf.d/diff-cestovani.ini:ro" >&2
        echo "    environment: GAMECON_DIFF_DATUM: \"\${GAMECON_DIFF_DATUM:-}\"" >&2
        exit 1
    fi
}

nastav() {
    local datum="$1" dir label
    for label in legacy novy; do
        [ "$label" = legacy ] && dir="$LEGACY" || dir="$NOVY"
        overWiring "$dir"
        zaridIni "$dir"
    done

    posun_nabizet_do "$datum"
    for label in legacy novy; do
        [ "$label" = legacy ] && dir="$LEGACY" || dir="$NOVY"
        # .env je gitignorovaný, takže na čerstvém worktree nemusí existovat; `sed -i`
        # by na chybějícím souboru skončil a druhá větev by zůstala neposunutá.
        touch "$dir/.env"
        sed -i '/^GAMECON_DIFF_DATUM=/d' "$dir/.env"
        [ -n "$datum" ] && printf 'GAMECON_DIFF_DATUM=%s\n' "$datum" >> "$dir/.env"
        # Proměnná prostředí se propíše až při recreate kontejneru. Stderr si necháváme,
        # ať je při selhání vidět proč.
        (cd "$dir" && docker compose up -d --no-deps web >/dev/null)
    done
}

case "${1:-}" in
    --ukaz)
        for label in legacy novy; do
            [ "$label" = legacy ] && dir="$LEGACY" || dir="$NOVY"
            v=$(grep '^GAMECON_DIFF_DATUM=' "$dir/.env" 2>/dev/null | cut -d= -f2 || true)
            echo "$label: ${v:-<skutečný čas>}"
        done
        exit 0 ;;
    --vrat)
        nastav ""
        echo "✓ zpět na skutečné termíny"
        exit 0 ;;
    '') echo "použití: bin-diff/cas.sh <YYYY-MM-DD> | --ukaz | --vrat" >&2; exit 1 ;;
esac

nastav "$1"
echo "✓ obě větve se chovají, jako by bylo $1"
