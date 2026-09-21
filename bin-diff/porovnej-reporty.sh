#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

# Porovná výstup všech reportů na obou větvích.
#
# Reporty čtou e-shopové tabulky napříč celou administrativou, takže jsou to ony, na
# kterých se přechod na product/variant pozná nejdřív. `porovnej.sh` srovnává data v DB;
# tenhle skript srovnává, co z nich reporty spočítají.
#
#   bin-diff/porovnej-reporty.sh                 všechny reporty
#   bin-diff/porovnej-reporty.sh finance-%       jen odpovídající skripty (SQL LIKE)
#   bin-diff/porovnej-reporty.sh --ulozit ~/kam  nechá stažené CSV na disku k prohlédnutí
#
# Seznam se bere z tabulky `reporty`, ne z výpisu adresáře: quick-N reporty jsou uložené
# jen v DB a žádný soubor jim neodpovídá. Nový report se tím pokryje sám od sebe.

# shellcheck source=vetve.sh
source "$(dirname "${BASH_SOURCE[0]}")/vetve.sh"

ULOZIT=""
VZOR="%"
while [ $# -gt 0 ]; do
    case "$1" in
        --ulozit) ULOZIT="${2:-}"; shift 2 ;;
        -h|--help) sed -n '6,18p' "${BASH_SOURCE[0]}" | sed 's/^# \?//'; exit 0 ;;
        *) VZOR="$1"; shift ;;
    esac
done

OUT="${TMPDIR:-/tmp}/eshop-reporty.$$"
mkdir -p "$OUT"
if [ -n "$ULOZIT" ]; then
    mkdir -p "$ULOZIT/novy" "$ULOZIT/legacy"
fi
trap 'rm -rf "$OUT"' EXIT

# Port si řekne compose. Napevno zapsaný port platí jen pro slot, ve kterém skript
# vznikl, a na jiném worktree by se skript mlčky ptal cizí větve.
portVetve() {
    local dir="$1" port
    port=$(docker compose --project-directory "$dir" port web 80 2>/dev/null | sed 's/.*://')
    if [ -z "$port" ]; then
        echo "✗ Ve '$dir' neběží web (docker compose port web 80 nic nevrátil)." >&2
        echo "  Nastartuj obě větve: docker compose --project-directory '$dir' up -d" >&2
        exit 1
    fi
    printf '%s' "$port"
}

# Dotaz jde přes `bin-docker/php` té které větve, ne přes `docker compose exec` napřímo:
# služba se jmenuje `web` (ne `php`), potřebuje workdir a běží pod www-data, a wrapper
# tohle všechno řeší — navíc kontejner nastartuje, když zrovna neběží.
sql() {
    local dir="$1" dotaz="$2"
    (cd "$dir" && ./bin-docker/php ./bin/console dbal:run-sql --no-ansi "$dotaz" 2>/dev/null)
}

# Reporty jsou za právem ADMINISTRACE_REPORTY a `admin` z docker-compose.override ho nemá
# — vrací „Nemáš právo 104“, což vypadá jako prázdný report, ne jako chybějící přihlášení.
# Účet se proto hledá v DB podle práva. Konkrétní login je z produkčního dumpu, takže se
# nikam nevypisuje ani nezapisuje do skriptu.
PRAVO_REPORTY=104
uzivatelSPravem() {
    local dir="$1" login
    login=$(sql "$dir" \
        "SELECT uzivatele_hodnoty.login_uzivatele
         FROM uzivatele_hodnoty
         JOIN platne_role_uzivatelu ON platne_role_uzivatelu.id_uzivatele = uzivatele_hodnoty.id_uzivatele
         JOIN prava_role ON prava_role.id_role = platne_role_uzivatelu.id_role
         WHERE prava_role.id_prava = $PRAVO_REPORTY
         ORDER BY uzivatele_hodnoty.id_uzivatele
         LIMIT 1" | sed -n '4p' | tr -d ' |')
    if [ -z "$login" ]; then
        echo "✗ Ve '$dir' nemá právo $PRAVO_REPORTY žádný uživatel." >&2
        echo "  Nahraj produkční dump (bin-diff/reset.sh) — na prázdné DB reporty porovnat nejde." >&2
        exit 1
    fi
    printf '%s' "$login"
}

# Nepřihlášení nejde poznat podle stavového kódu — administrace vrátí přihlašovací
# formulář jako 200. Ověřuje se proto pozitivně: po přihlášení musí jít stáhnout report
# s daty. Bez téhle kontroly se nepřihlášený běh tváří jako řada reportů „bez dat“.
overPrihlaseni() {
    local port="$1" jar="$2" dir="$3" odpoved
    odpoved=$(curl -sS --max-time 60 -b "$jar" -c "$jar" \
        "http://localhost:$port/admin/reporty/finance-report-sirien?format=csv" 2>/dev/null || true)
    case "$odpoved" in
        *hesloNAdm*|*'Nemáš právo'*|*'Nemáš potřebné'*|'')
            echo "✗ Přihlášení do '$dir' neprošlo — administrace vrací přihlašovací stránku." >&2
            echo "  Zkontroluj, že je v docker-compose.override.yml nastavené UNIVERZALNI_HESLO=admin." >&2
            exit 1
            ;;
    esac
}

prihlas() {
    local port="$1" jar="$2" login
    login="$3"
    # Přihlašovací POST musí jít na adresu, která se už nepřesměrovává: `/admin` odpoví
    # 302 → 301 a tělo požadavku se po cestě ztratí, takže se přihlášení tiše neprovede
    # a všechny reporty se pak stáhnou jako přihlašovací stránka.
    curl -sS -c "$jar" -o /dev/null "http://localhost:$port/admin/?req=" || true
    curl -sS -c "$jar" -b "$jar" -o /dev/null \
        --data-urlencode "loginNAdm=$login" --data-urlencode 'hesloNAdm=admin' \
        "http://localhost:$port/admin/?req=" || true
}

# Reporty se stahují jako CSV: xlsx je zabalený ZIP s časem uložení uvnitř, takže by se
# dva shodné reporty lišily bajtově, a HTML nese navíc celé menu administrace.
# Report, který skončí chybou nebo spadne na timeout, nesmí shodit celý běh — jinak se
# porovnání zastaví na prvním problémovém reportu a o zbylých se nedozvíme nic. Selhání
# se propíše prázdným souborem a ohlásí se u toho reportu.
# Sloupec `reporty.skript` není kus URL. Quick reporty jsou v něm uložené jako `quick-100`,
# ale administrace je servíruje na `reporty/quick?id=100` — na `reporty/quick-100` žádná
# routa nesedí a admin místo reportu vrátí rozcestník se seznamem reportů. Ten se pak
# „očistí“ na nula řádků a celé to vypadá, že report jen nevrátil data.
urlReportu() {
    local skript="$1"
    case "$skript" in
        quick-*) printf 'quick?id=%s&format=csv' "${skript#quick-}" ;;
        *) printf '%s?format=csv' "$skript" ;;
    esac
}

stahni() {
    local port="$1" jar="$2" skript="$3" kam="$4"
    local stav
    # Stav se čte zvlášť místo `curl -f`, protože `-f` zahodí tělo odpovědi — a právě
    # v něm bývá Tracy stránka s hláškou, podle které se pozná, co se rozbilo.
    # Samotný `curl` bez toho vrací 0 i na 500, takže by se chybová stránka uložila
    # jako by to byl report.
    stav=$(curl -sS --max-time 180 -b "$jar" -c "$jar" -o "$kam" -w '%{http_code}' \
        "http://localhost:$port/admin/reporty/$(urlReportu "$skript")" 2>/dev/null) || stav=000
    case "$stav" in
        2*) return 0 ;;
        *) return 1 ;;
    esac
}

# Dev prostředí připíná za odpověď Tracy (na produkci vypnutá). U reportu, který někde
# vyhodí notice, se navíc Tracy vloží doprostřed CSV a zbytek řádků se nestáhne — obě
# větve stejně, takže porovnání to nekazí, ale bajtově se Tracy liší při každém běhu
# (jiné id dumpu), takže se musí odstřihnout. `strip_tags(): Passing null` v report.php
# je starší bug, nesouvisí s e-shopem.
#
# Řeže se na prvním řádku začínajícím `<`, ne na prvním výskytu slova „tracy“: hlavička
# té vložené stránky nese číslo portu a cestu k souboru, takže by se každý takový report
# hlásil jako rozdíl jen proto, že větve běží na jiném portu.
#
# Reporty vkládají do dat odkazy do administrace včetně hostitele a portu, a každá větev
# běží na jiném portu. Bez sjednocení se takový report hlásí jako rozdíl na všech řádcích
# a skutečný nález se v tom ztratí — přesně to schovalo rozdíl v jednom zůstatku mezi
# 1161 jinak shodnými řádky.
#
# Anonymní nákupy: migrace anonymous-buyer je přeřadila ze SYSTEM (1) na ANONYM (0).
# Je to záměrná změna, kterou stejně normalizuje i `porovnej.sh` — bez toho hlásí rozdíl
# každý report, který ukazuje kupujícího, a skutečné nálezy se v tom ztratí.
#
# Některé reporty si do dat píšou čas svého vzniku. Obě větve se stahují po sobě, takže
# se ten čas skoro vždy liší o vteřiny. Nahrazuje se proto jen na řádku, který ho nese
# (`Ir-Timestamp`) — plošné maskování všech datumů s časem by umlčelo i skutečné údaje
# z dat, a těch je násobně víc (jen odhlášené objednávky jich mají 457).
#
# Pořadí řádků není v reportech dané (GROUP BY bez ORDER BY), takže se ještě třídí;
# jinak by se hlásil rozdíl u reportu, který vrátil tytéž řádky jinak seřazené.
# Ukázka rozdílu jde do terminálu a odtud často do issue nebo PR, a reporty jsou plné
# osobních údajů skutečných účastníků z produkčního dumpu. K rozhodnutí „rozešlo se to“
# stačí číslo řádku a sloupec, který se liší, takže se osobní údaje nahrazují značkou.
#
# POZOR na hranice téhle ochrany: spolehlivě se maskuje e-mail, telefon v uvozovkách,
# id v odkazu do administrace, přezdívka v uvozovkách a dvojice jméno/příjmení na
# začátku řádku nebo vedle e-mailu. Osamocené jméno v uvozovkách uprostřed jinak
# neosobního reportu se maskuje jen v úzkém tvaru „dvě slova, obě s velkým písmenem“
# (`"Jan Novák"`), protože širší pravidlo sežere i názvy zboží — a `"Tričko účastnické
# XXXL"` je přesně ten údaj, kvůli kterému se reporty porovnávají. Tříslovné jméno nebo
# jméno bez uvozovek tedy projde. Než se výstup vloží do veřejného repozitáře, projdi ho
# očima; skript na to sám upozorní.
#
# Maskuje se podle tvaru hodnoty, ne podle pozice sloupce: každý report má jiné pořadí
# sloupců, takže pravidlo „druhý a třetí sloupec je jméno“ platí jen u některých a jinde
# mlčky propustí celý řádek i s e-maily a telefony.
#
# Každé pravidlo musí být úzké: telefon se poznává jen v uvozovkách a po trojicích se
# dvěma mezerami, protože volnější tvar („devět číslic za sebou“) sežere i částky,
# variabilní symboly a počty kusů — tedy přesně ta čísla, kvůli kterým se reporty
# porovnávají, a v rozdílu pak obě strany vypadají stejně.
maskuj() {
    sed -E \
        -e 's/[[:alnum:]._%+-]+@[[:alnum:].-]+\.[[:alpha:]]{2,}/<MAIL>/g' \
        -e 's/"(\+?[0-9]{3} ?)?[0-9]{3} [0-9]{3} [0-9]{3} ?"/"<TELEFON>"/g' \
        -e 's/(pracovni_uzivatel|id_uzivatele)=[0-9]+/\1=<ID>/g' \
        -e 's/"[^";]*„[^"“]*“[^";]*"/"<UZIVATEL>"/g' \
        -e 's/"[^";]+"(;[^;]*)?;<MAIL>/"<UZIVATEL>"\1;<MAIL>/g' \
        -e 's/^([0-9]+);[^;"]+;[[:upper:]][^;"]*;[[:upper:]][^;"]*;/\1;<LOGIN>;<JMENO>;<PRIJMENI>;/' \
        -e 's/(^|;)(\+420)?[0-9]{9}(;|$)/\1<TELEFON>\3/g' \
        -e 's/(^|;)"[[:upper:]][^"; ]+ [[:upper:]][^"; ]+";/\1"<UZIVATEL>";/g'
}

ocisti() {
    sed '/^[[:space:]]*</,$d' "$1" \
        | sed -E 's#https?://[^;\"]*localhost:[0-9]+#<HOST>#g' \
        | sed -E '/^Ir-Timestamp;/ s/[0-9]{4}-[0-9]{2}-[0-9]{2}[ T][0-9]{2}:[0-9]{2}(:[0-9]{2})?/<CAS>/g' \
        | sed -E 's/;(1;SYSTEM;SYSTEM|0;ANONYM;ANONYM);/;<ANONYM>;/g' \
        | LC_ALL=C sort
}

# Návratová hodnota se musí zachytit do proměnné dřív, než se použije jako argument:
# `exit 1` uvnitř `$( )` ukončí jen podshell, takže volání `f "$(guard)"` po selhání
# guardu vesele pokračuje s prázdnou hodnotou a `set -e` to nezastaví. U přihlášení to
# bylo obzvlášť zákeřné — nepřihlášený běh stáhne z obou větví přihlašovací stránku,
# `ocisti` ji zahodí celou a výsledek se ohlásí jako „neporovnáno“ s návratovým kódem 0,
# tedy jako čistý běh.
NOVY_PORT=$(portVetve "$NOVY")
LEGACY_PORT=$(portVetve "$LEGACY")
NOVY_JAR="$OUT/novy.jar"
LEGACY_JAR="$OUT/legacy.jar"

echo "nová větev:  $NOVY (:$NOVY_PORT)"
echo "legacy:      $LEGACY (:$LEGACY_PORT)"

# „Unknown column“ v reportu nemusí znamenat chybu ve větvi: když je jedna DB nahraná ze
# staršího dumpu než druhá, chybí jí sloupce, které do schématu přidal `migrace/000.php`,
# a report spadne jen tam. Než se takový nález nahlásí jako regrese, patří ověřit, že obě
# DB vznikly ze stejného dumpu a mají dojeté migrace (`bin-diff/reset.sh`).

NOVY_LOGIN=$(uzivatelSPravem "$NOVY")
LEGACY_LOGIN=$(uzivatelSPravem "$LEGACY")
prihlas "$NOVY_PORT" "$NOVY_JAR" "$NOVY_LOGIN"
prihlas "$LEGACY_PORT" "$LEGACY_JAR" "$LEGACY_LOGIN"
overPrihlaseni "$NOVY_PORT" "$NOVY_JAR" "$NOVY"
overPrihlaseni "$LEGACY_PORT" "$LEGACY_JAR" "$LEGACY"

# Seznam se bere z nové větve; kdyby na ní report chyběl, je to samo o sobě nález a
# projeví se prázdným výstupem, ne tichým přeskočením.
mapfile -t SKRIPTY < <(sql "$NOVY" \
    "SELECT skript FROM reporty WHERE skript LIKE '$VZOR' ORDER BY skript" \
    | sed -n '4,$p' | tr -d ' |' | grep -v '^$' | grep -v '^-*$')

if [ "${#SKRIPTY[@]}" -eq 0 ]; then
    echo "✗ Vzoru '$VZOR' neodpovídá žádný report v tabulce 'reporty'." >&2
    exit 1
fi

# Reporty, které při zobrazení něco vytvoří nebo změní, se porovnávat nedají: každé
# zavolání vrátí něco jiného a navíc by běh harnessu sám měnil data, se kterými se pak
# porovnává. `novy_slevovy_kod` vyrobí nový slevový kód a vrátí ho jako QR obrázek;
# `quick-77` je „Log použití reportů“, do kterého se zapisuje každé stažení — tedy i ta,
# která dělá tenhle skript, takže sám sobě mění porovnávaná data.
VYNECHAT=('novy_slevovy_kod' 'quick-77')
for vynechany in "${VYNECHAT[@]}"; do
    for i in "${!SKRIPTY[@]}"; do
        if [ "${SKRIPTY[$i]}" = "$vynechany" ]; then
            unset 'SKRIPTY[i]'
            echo "vynechávám $vynechany (mění data při každém zavolání)"
        fi
    done
done
SKRIPTY=("${SKRIPTY[@]}")

echo "porovnávám ${#SKRIPTY[@]} reportů…"
echo

SHODA=0; ROZDIL=0; PRAZDNE=0
ROZDILNE=()
for skript in "${SKRIPTY[@]}"; do
    NOVY_SELHAL=0; LEGACY_SELHAL=0
    stahni "$NOVY_PORT" "$NOVY_JAR" "$skript" "$OUT/n.csv" || NOVY_SELHAL=1
    stahni "$LEGACY_PORT" "$LEGACY_JAR" "$skript" "$OUT/l.csv" || LEGACY_SELHAL=1

    if [ -n "$ULOZIT" ]; then
        cp "$OUT/n.csv" "$ULOZIT/novy/$skript.csv"
        cp "$OUT/l.csv" "$ULOZIT/legacy/$skript.csv"
    fi

    if [ "$NOVY_SELHAL" = 1 ] || [ "$LEGACY_SELHAL" = 1 ]; then
        printf '  ✗  %-52s nestáhl se (nová %s, legacy %s)\n' "$skript" \
            "$([ "$NOVY_SELHAL" = 1 ] && echo chyba || echo ok)" \
            "$([ "$LEGACY_SELHAL" = 1 ] && echo chyba || echo ok)"
        ROZDIL=$((ROZDIL + 1))
        ROZDILNE+=("$skript")
        continue
    fi

    ocisti "$OUT/n.csv" > "$OUT/n.cist"
    ocisti "$OUT/l.csv" > "$OUT/l.cist"

    # Report bez jediného řádku na OBOU větvích neporovnává nic. Hlásí se zvlášť a s
    # důvodem, aby se „nic se nerozešlo“ nepletlo s „nic se neporovnalo“ — většina
    # quick-* reportů čeká na parametr (ročník, filtr), který z URL nedostane, a bez
    # rozlišení by 64 nespuštěných reportů vypadalo jako 64 shod.
    if [ ! -s "$OUT/n.cist" ] && [ ! -s "$OUT/l.cist" ]; then
        duvod='bez dat'
        if grep -qi 'zahájeno' "$OUT/n.csv"; then
            duvod='generuje se na pozadí'
        elif grep -qi 'Nemáš právo\|Nemáš potřebné' "$OUT/n.csv"; then
            duvod='chybí právo'
        elif grep -q 'Univerzální reporty' "$OUT/n.csv"; then
            # Rozcestník místo reportu znamená, že sestavená URL na nic nesedí — ne že
            # report nemá data. Bez téhle větve to splyne s „bez dat“ a chyba v URL se
            # tváří jako vlastnost reportu.
            duvod='URL nesedí na žádný report (vrátil se rozcestník)'
        fi
        printf '  ?  %-52s %s\n' "$skript" "$duvod"
        PRAZDNE=$((PRAZDNE + 1))
        continue
    fi

    if cmp -s "$OUT/n.cist" "$OUT/l.cist"; then
        printf '  ✓  %-52s %s řádků\n' "$skript" "$(wc -l < "$OUT/n.cist")"
        SHODA=$((SHODA + 1))
    else
        printf '  ✗  %-52s nová %s / legacy %s řádků\n' "$skript" \
            "$(wc -l < "$OUT/n.cist")" "$(wc -l < "$OUT/l.cist")"
        # `|| true`: diff vrací 1 při rozdílu a `head` zavře rouru, což by pod `set -e`
        # ukončilo celý cyklus po prvním nálezu — zbylé reporty by se mlčky neporovnaly.
        diff "$OUT/l.cist" "$OUT/n.cist" 2>/dev/null | head -12 | maskuj | sed 's/^/       /' || true
        ROZDIL=$((ROZDIL + 1))
        ROZDILNE+=("$skript")
    fi
done

echo
echo "shoda $SHODA · rozdíl $ROZDIL · neporovnáno $PRAZDNE"
if [ "$PRAZDNE" -gt 0 ]; then
    echo "(neporovnané reporty nejsou ověřené — nevrátily data bez parametru)"
fi
if [ -n "$ULOZIT" ]; then
    echo "CSV uloženo do $ULOZIT/{novy,legacy}/"
fi

if [ "$ROZDIL" -gt 0 ]; then
    echo
    echo "rozešlo se: ${ROZDILNE[*]}"
    echo
    echo "Maskování osobních údajů není úplné — než ukázky rozdílů vložíš do issue nebo PR,"
    echo "přečti si je (viz komentář u maskuj())."
    exit 1
fi
