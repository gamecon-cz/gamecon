#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

# Obnoví obě porovnávané databáze do výchozího stavu scénářů.
#
# Obnovuje se ze snapshotu pořízeného PO migracích, ne z dumpu ostré: nahrát 33MB dump a
# pustit na něm migrace jsou jednotky minut, snapshot je sekundy. Snapshot se pořídí sám
# při prvním běhu z aktuálního stavu, takže první spuštění musí proběhnout nad čerstvě
# nasazenými DB.
#
#   bin-diff/reset.sh            obě větve
#   bin-diff/reset.sh legacy     jen A
#   bin-diff/reset.sh novy       jen B
#   bin-diff/reset.sh --snapshot znovu pořídit snapshoty z aktuálního stavu

# shellcheck source=vetve.sh
source "$(dirname "${BASH_SOURCE[0]}")/vetve.sh"
SNAP_DIR="$NOVY/dumps/diff-snapshots"

mkdir -p "$SNAP_DIR"

# Dumpuje se stranou a na místo se přesune, až když dump projde. Psát rovnou do cílového
# souboru znamená, že selhaný dump po sobě nechá kus archivu — a další běh ho vezme jako
# platný snapshot, dropne databázi a obnoví z něj. Proto se ani nezahazuje stderr: když
# dump selže, chceme vidět proč.
snapshot() {
    local dir="$1" jmeno="$2" rozdelany
    rozdelany="$SNAP_DIR/$jmeno.sql.gz.rozdelany"
    echo "→ snapshot $jmeno"
    if ! docker compose --project-directory "$dir" exec -T sql.gamecon \
        mariadb-dump -uroot -p"$(rootHeslo "$dir")" --single-transaction --routines --events gamecon \
        | gzip > "$rozdelany"; then
        rm -f "$rozdelany"
        echo "✗ '$dir': snapshot se nepovedl, nechávám předchozí beze změny." >&2
        return 1
    fi
    mv "$rozdelany" "$SNAP_DIR/$jmeno.sql.gz"
}

# Tenhle skript dropuje databázi, takže si napřed ověří, že míří na jednorázový vývojový
# kontejner. Na hostu gamecon.cz stojí vedle sebe produkce (`d16779_*`) i archivní ročníky
# a DROP tam už jednou shodil ostrou; ověřit cíl je levnější než ta oprava.
overCilJeJednorazovaDb() {
    local dir="$1" kontejner seznam cizi

    kontejner=$(docker compose --project-directory "$dir" ps -q sql.gamecon 2>/dev/null) || true
    if [ -z "$kontejner" ]; then
        echo "✗ '$dir': kontejner sql.gamecon neběží." >&2
        return 1
    fi

    # Ptáme se na celý seznam databází a rozhodujeme z něj. Hledat rovnou jen produkční
    # by znamenalo, že selhaný dotaz vrátí prázdno úplně stejně jako čistý kontejner —
    # a guard by drop pustil dál právě ve chvíli, kdy neví, kam míří.
    if ! seznam=$(docker exec -i "$kontejner" mariadb -uroot -p"$(rootHeslo "$dir")" -N -B \
        -e 'SHOW DATABASES;' 2>&1); then
        echo "✗ '$dir': nejde získat seznam databází, nedropuji:" >&2
        echo "$seznam" | sed 's/^/    /' >&2
        return 1
    fi

    # Produkční ani archivní databáze nemá vedle sebe co dělat. Když tam je, ukazuje
    # compose někam jinam, než si myslíme, a dropovat se nesmí nic.
    if cizi=$(echo "$seznam" | grep -E '^(d16779_|gamecon_[0-9])'); then
        echo "✗ '$dir': v kontejneru jsou produkční/archivní databáze, nedropuji:" >&2
        echo "$cizi" | sed 's/^/    /' >&2
        return 1
    fi

    # Bez `gamecon` není co obnovovat, takže její absence znamená, že se díváme jinam.
    if ! echo "$seznam" | grep -qx 'gamecon'; then
        echo "✗ '$dir': v kontejneru není databáze 'gamecon', nedropuji." >&2
        return 1
    fi
}

obnov() {
    local dir="$1" jmeno="$2"
    if [ ! -f "$SNAP_DIR/$jmeno.sql.gz" ]; then
        echo "→ $jmeno: snapshot chybí, pořizuji z aktuálního stavu"
        snapshot "$dir" "$jmeno"
        return
    fi
    overCilJeJednorazovaDb "$dir" || exit 1
    echo "→ obnovuji $jmeno"
    # Tahle větev databázi dropuje, takže chyby nesmí mizet: kdyby zmizely, skript umře
    # hned po „obnovuji“ bez důvodu a databáze je v tu chvíli prázdná.
    # Jen tahle lokální DB ve vlastním kontejneru worktree; dump žádnou DB nepojmenovává.
    if ! chyba=$(docker compose --project-directory "$dir" exec -T sql.gamecon \
        mariadb -uroot -p"$(rootHeslo "$dir")" -e \
        "DROP DATABASE IF EXISTS gamecon; CREATE DATABASE gamecon CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;" 2>&1); then
        echo "✗ '$dir': nepovedlo se založit prázdnou databázi:" >&2
        sed 's/^/    /' <<<"$chyba" >&2
        exit 1
    fi
    if ! chyba=$(gunzip -c "$SNAP_DIR/$jmeno.sql.gz" \
        | docker compose --project-directory "$dir" exec -T sql.gamecon \
          mariadb -uroot -p"$(rootHeslo "$dir")" gamecon 2>&1); then
        echo "✗ '$dir': obnova ze snapshotu selhala, databáze je prázdná:" >&2
        sed 's/^/    /' <<<"$chyba" >&2
        exit 1
    fi
}

case "${1:-vse}" in
    --snapshot)
        snapshot "$LEGACY" legacy
        snapshot "$NOVY" novy
        ;;
    legacy) obnov "$LEGACY" legacy ;;
    novy)   obnov "$NOVY" novy ;;
    vse)
        obnov "$LEGACY" legacy
        obnov "$NOVY" novy
        ;;
    *) echo "neznámý argument: $1" >&2; exit 1 ;;
esac

echo "✓ hotovo"
