#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

# Porovná ceny v e-shopu mezi legacy a novou větví: tentýž `Cenik::cena()` pustí na obou
# pro 6 rolí × 8 předmětů z každého typu a výsledky diffne.
#
# Klíčem porovnání je `id_predmetu`, ne název — „Oběd čtvrtek" existuje v osmi ročníkových
# variantách od 85 do 140 Kč a řazení podle názvu vrací na každé větvi jinou osmičku.

# shellcheck source=vetve.sh
source "$(dirname "${BASH_SOURCE[0]}")/vetve.sh"
OUT="${TMPDIR:-/tmp}/eshop-ceny.$$"
mkdir -p "$OUT"

# Obě větve měří tímtéž skriptem — tím, co leží vedle tohohle souboru. Brát ho z $NOVY
# znamenalo měřit verzí, která je v jiném worktree, takže oprava v bin-diff/ se neprojevila
# a měřilo se dál starým kódem.
SKRIPT="$(dirname "${BASH_SOURCE[0]}")/ceny.php"

# Kopie skriptu musí zmizet i když běh spadne — jinak zůstane ležet v cizím worktree.
uklid() { rm -rf "$OUT" "$LEGACY/symfony/var/ceny.php" "$NOVY/symfony/var/ceny.php"; }
trap uklid EXIT

for kde in "$LEGACY:legacy" "$NOVY:novy"; do
    dir="${kde%%:*}"; jmeno="${kde##*:}"
    cp "$SKRIPT" "$dir/symfony/var/ceny.php"
    # Chybový výstup jde stranou, ne do dat: deprecation z PHP by se stal řádkem, `awk`
    # by z něj udělal klíč a obě větve běží jiný kód, takže by se lišily kvůli hlášce.
    (cd "$dir" && ./bin-docker/php symfony/var/ceny.php) > "$OUT/$jmeno.txt" 2>"$OUT/$jmeno.err" \
        || { echo "✗ $jmeno selhal:"; tail -3 "$OUT/$jmeno.err" "$OUT/$jmeno.txt"; exit 1; }
    rm -f "$dir/symfony/var/ceny.php"
    if [ -s "$OUT/$jmeno.err" ]; then
        echo "⚠ $jmeno psal na chybový výstup (do porovnání to nejde):" >&2
        head -3 "$OUT/$jmeno.err" | sed 's/^/    /' >&2
    fi
    # Bereme jen řádky v očekávaném tvaru (pět polí oddělených |), ať se do klíče
    # nedostane nic, co není změřená cena.
    awk -F'|' 'NF == 5 {gsub(/^ +| +$/,"",$1);gsub(/^ +| +$/,"",$3);gsub(/^ +| +$/,"",$5); print $1"|"$3"|"$5}' \
        "$OUT/$jmeno.txt" | sort > "$OUT/$jmeno.key"
done

if diff -q "$OUT/legacy.key" "$OUT/novy.key" > /dev/null; then
    echo "✓ ceny se shodují ($(wc -l < "$OUT/novy.key") kombinací role × předmět)"
else
    echo "✗ ROZDÍLY (role|id_predmetu|cena):"
    diff "$OUT/legacy.key" "$OUT/novy.key"
    exit 1
fi
