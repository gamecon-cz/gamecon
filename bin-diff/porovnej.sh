#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

# Sémanticky porovná data na obou větvích.
#
# Porovnávat po sloupcích nejde — nová vrstva má o 10 tabulek a několik sloupců navíc
# (variant_id, order_id, snapshoty). Porovnává se proto průmět, který dává smysl na obou:
# kdo má co koupené, za kolik a v jakém ročníku, plus ubytovací pole v uzivatele_hodnoty.
#
#   bin-diff/porovnej.sh              celý průmět
#   bin-diff/porovnej.sh 1234         jen jeden uživatel (rychlé při scénáři)

# shellcheck source=vetve.sh
source "$(dirname "${BASH_SOURCE[0]}")/vetve.sh"
OUT="${TMPDIR:-/tmp}/eshop-diff.$$"
mkdir -p "$OUT"
trap 'rm -rf "$OUT"' EXIT

# Argument jde přímo do SQL, takže se musí ověřit, že je to opravdu jen číslo. Překlep
# jako `porovnej.sh '1 OR 1=1'` by jinak tiše rozšířil průmět a vrátil věrohodně
# vypadající, ale jiné porovnání, než jsme chtěli.
KDE_UZIVATEL=""
if [ $# -ge 1 ]; then
    if ! [[ $1 =~ ^[0-9]+$ ]]; then
        echo "✗ '$1' není id uživatele — čekám celé číslo." >&2
        exit 1
    fi
    # Filtruje se přes totéž srovnání anonyma jako průmět níž. Na holý sloupec by
    # `porovnej.sh 1` vzal na legacy 24 anonymních nákupů a na nové větvi žádný (a u `0`
    # naopak), takže by hlásil rozdíl, který dělá jen ten filtr.
    KDE_UZIVATEL="AND IF(n.id_uzivatele = 0, 1, n.id_uzivatele) = $1"
fi

# cena_nakupni se srovnává na 2 desetinná místa: nová vrstva má NUMERIC(6,2), legacy
# volnější typ, takže holé porovnání by hlásilo rozdíl u každého řádku.
# Anonymní nákupy: migrace anonymous-buyer je přeřadila ze SYSTEM (1) na ANONYM (0).
# Je to záměrná změna, tak se obě strany srovnávají na id 1, jinak by 24 řádků hlásilo
# rozdíl při každém běhu a skutečné nálezy by se v tom ztratily.
#
# Koupenou věc nese na legacy řádek katalogu, na nové větvi varianta (nákup už `id_predmetu`
# nemá), takže se obě strany srovnávají podle kódu. Kód varianty je kód původního řádku, jen
# starší duplicity dostaly při převodu na varianty příponu `_<id původního řádku>`
# (`kostka_fate_2021` → `kostka_fate_2021_646`); ta se na nové straně odřízne jen tehdy, když
# zbytek je kódem jiné varianty — `ponozky_..._42_45` nebo `kostka_2022` tak zůstanou celé.
PRUMET_NAKUPY_LEGACY="
SELECT CONCAT_WS('|', IF(n.id_uzivatele = 0, 1, n.id_uzivatele), koupene.kod_predmetu, n.rok,
                 FORMAT(n.cena_nakupni, 2), COUNT(*))
FROM shop_nakupy n
INNER JOIN shop_predmety AS koupene ON koupene.id_predmetu = n.id_predmetu
WHERE 1=1 $KDE_UZIVATEL
GROUP BY IF(n.id_uzivatele = 0, 1, n.id_uzivatele), koupene.kod_predmetu, n.rok, FORMAT(n.cena_nakupni, 2)
ORDER BY IF(n.id_uzivatele = 0, 1, n.id_uzivatele), koupene.kod_predmetu, n.rok, FORMAT(n.cena_nakupni, 2);"
PRUMET_NAKUPY_NOVY="
SELECT CONCAT_WS('|', nakupy.uzivatel, nakupy.kod, nakupy.rok, nakupy.cena, COUNT(*))
FROM (
    SELECT IF(n.id_uzivatele = 0, 1, n.id_uzivatele) AS uzivatel,
           IF(EXISTS (SELECT 1 FROM product_variant AS puvodni
                      WHERE puvodni.code = REGEXP_REPLACE(koupene.code, '_[0-9]+\$', '')
                        AND puvodni.id <> koupene.id),
              REGEXP_REPLACE(koupene.code, '_[0-9]+\$', ''),
              koupene.code) AS kod,
           n.rok,
           FORMAT(n.cena_nakupni, 2) AS cena
    FROM shop_nakupy n
    INNER JOIN product_variant AS koupene ON koupene.id = n.variant_id
    WHERE 1=1 $KDE_UZIVATEL
) AS nakupy
GROUP BY nakupy.uzivatel, nakupy.kod, nakupy.rok, nakupy.cena
ORDER BY nakupy.uzivatel, nakupy.kod, nakupy.rok, nakupy.cena;"

# Filtr na uživatele musí platit i tady, jinak `porovnej.sh <id>` porovná jednoho člověka
# v nákupech a celou tabulku v ubytování. Podmínky jdou do závorky — bez ní by `OR` uvnitř
# filtr obešel a vrátil zase všechny.
PRUMET_UBYTOVANI="
SELECT CONCAT_WS('|', id_uzivatele, COALESCE(ubytovan_s,''), COALESCE(nechce_ubytovani,0))
FROM uzivatele_hodnoty AS n
WHERE (COALESCE(ubytovan_s,'') <> '' OR COALESCE(nechce_ubytovani,0) <> 0) $KDE_UZIVATEL
ORDER BY id_uzivatele;"

vytahni() {
    local dir="$1" soubor="$2" sql="$3" chyba
    chyba="$OUT/chyba"
    # Chybu dotazu je potřeba vidět: bez ní se selhání projeví jako „0 řádků“ a vypadá
    # jako prázdná databáze, ne jako dotaz, který vůbec neproběhl.
    if ! docker compose --project-directory "$dir" exec -T sql.gamecon \
        mariadb -uroot -p"$(rootHeslo "$dir")" gamecon -N -B -e "$sql" 2>"$chyba" \
        | tr -d '\r' | LC_ALL=C sort > "$soubor"; then
        echo "✗ '$dir': dotaz selhal:" >&2
        sed 's/^/    /' "$chyba" >&2
        return 1
    fi
}

porovnej_cast() {
    local nazev="$1" sql_legacy="$2" sql_novy="${3:-$2}"
    # Volá se jako `porovnej_cast … || …`, takže uvnitř neplatí `set -e` a selhaný dotaz
    # by se jinak propsal jen jako prázdný soubor.
    if ! vytahni "$LEGACY" "$OUT/legacy" "$sql_legacy" || ! vytahni "$NOVY" "$OUT/novy" "$sql_novy"; then
        printf '  %-22s NEPROBĚHLO — dotaz selhal\n' "$nazev"
        return 1
    fi

    local pocet_l pocet_n
    pocet_l=$(wc -l < "$OUT/legacy"); pocet_n=$(wc -l < "$OUT/novy")

    # Prázdno na obou stranách projde diffem jako shoda, ale nad celým průmětem to spíš
    # znamená, že jsme se ptali jiné databáze, než jsme chtěli — shoda nad ničím není
    # důkaz. Se zúženým filtrem je to naopak běžné (uživatel bez nákupů) a chybou není.
    if [ -z "$KDE_UZIVATEL" ] && [ "$pocet_l" -eq 0 ] && [ "$pocet_n" -eq 0 ]; then
        printf '  %-22s PRÁZDNO na obou stranách — není co porovnávat\n' "$nazev"
        return 1
    fi

    if diff -q "$OUT/legacy" "$OUT/novy" >/dev/null; then
        printf '  %-22s shoda (%s řádků)\n' "$nazev" "$pocet_l"
        return 0
    fi

    printf '  %-22s ROZDÍL (legacy %s / nový %s)\n' "$nazev" "$pocet_l" "$pocet_n"
    echo '  --- jen v legacy ---'
    comm -23 "$OUT/legacy" "$OUT/novy" | head -15 | sed 's/^/    /'
    echo '  --- jen v novém ---'
    comm -13 "$OUT/legacy" "$OUT/novy" | head -15 | sed 's/^/    /'
    return 1
}

echo "=== sémantické porovnání A (legacy) vs B (nový) ==="
# Obě části projdou vždycky, ať je vidět celý obrázek, ale nenulový výsledek se musí
# propsat do exit kódu — jinak `porovnej.sh && echo OK` ohlásí úspěch nad rozdílem.
nalezen_rozdil=0
porovnej_cast "nákupy" "$PRUMET_NAKUPY_LEGACY" "$PRUMET_NAKUPY_NOVY" || nalezen_rozdil=1
porovnej_cast "ubytování/spolubydlící" "$PRUMET_UBYTOVANI" || nalezen_rozdil=1
exit "$nalezen_rozdil"
