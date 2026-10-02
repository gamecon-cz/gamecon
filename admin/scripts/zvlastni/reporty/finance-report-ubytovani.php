<?php
/**
 * !!! TENTO REPORT JE POUŽÍVÁN V _ubytovani-a-dalsi-obcasne-infopultakoviny-import-ubytovani.php
 * ZMĚNY V REPORTU MUSÍME REFLEKTOVAT I TAM (nové sloupce nebo jiné pořadí obecně ne, ale změny názvů rozhodně ano) !!!
 */
require __DIR__ . '/sdilene-hlavicky.php';

use Gamecon\Role\Role;
use App\Enum\ProductTagCode;
use Gamecon\Cas\DateTimeCz;

$o = dbQuery(<<<SQL
SELECT
    uzivatele.id_uzivatele,
    uzivatele.login_uzivatele,
    uzivatele.jmeno_uzivatele,
    uzivatele.prijmeni_uzivatele,
    -- typ ubytování odvozený z kódu předmětu bez posledních 3 znaků (přípona dne, např. "_st"/"-ct");
    -- např. "1L_ct" → "1L", "Hd-1L-ne" → "Hd-1L", "spacak_st" → "spacak".
    -- Nezávisí na názvu předmětu (ten byl 2026 přejmenován na "Postel na …"), a je jednoznačný pro import zpět.
    GROUP_CONCAT(DISTINCT LEFT(product_variant.code, CHAR_LENGTH(product_variant.code) - 3)) as typ,
    IF (COUNT(product_variant.id) != (MAX(product_variant.accommodation_day) - MIN(product_variant.accommodation_day) +1 /* od 0 do 4, tedy 5 dní max */),
        GROUP_CONCAT(CONCAT_WS(' ', typ_pokoje.nazev, product_variant.name)),
        ''
    ) AS mezera_v_ubytovani,
    MIN(product_variant.accommodation_day) as prvni_noc,
    MAX(product_variant.accommodation_day) as posledni_noc,
    GROUP_CONCAT(DISTINCT IF(ubytovani.pokoj = '', NULL, ubytovani.pokoj)) as pokoj,
    uzivatele.ubytovan_s,
    '' AS pozice, -- placeholder kvůli pořadí, hodnotu dáme později, viz PHP foreach dále
    '' AS datum_narozeni, -- placeholder
    uzivatele.mesto_uzivatele,
    uzivatele.ulice_a_cp_uzivatele,
    uzivatele.typ_dokladu_totoznosti AS typ_dokladu,
    '' AS cislo_dokladu,
    uzivatele.statni_obcanstvi
FROM uzivatele_hodnoty uzivatele
JOIN platne_role_uzivatelu
    ON uzivatele.id_uzivatele=platne_role_uzivatelu.id_uzivatele AND platne_role_uzivatelu.id_role=$0 -- přihlášení na gc
JOIN shop_nakupy nakupy
    ON nakupy.id_uzivatele=uzivatele.id_uzivatele AND nakupy.rok=$1 -- nákupy tento rok
JOIN product_variant
    ON product_variant.id = nakupy.variant_id
JOIN product_product_tag
    ON product_product_tag.product_id = product_variant.product_id
JOIN product_tag
    ON product_tag.id = product_product_tag.tag_id AND product_tag.code = $2
JOIN shop_predmety AS typ_pokoje
    ON typ_pokoje.id_predmetu = product_variant.product_id
LEFT JOIN ubytovani
    ON ubytovani.id_uzivatele=uzivatele.id_uzivatele  -- info o číslech pokoje
        AND ubytovani.rok=$1
        AND ubytovani.den = product_variant.accommodation_day
GROUP BY uzivatele.id_uzivatele
ORDER BY id_uzivatele
SQL,
    [
        Role::PRIHLASEN_NA_LETOSNI_GC,
        ROCNIK,
        ProductTagCode::UBYTOVANI->value,
    ],
);

$vystup = [];
while ($r = $o->fetch(PDO::FETCH_ASSOC)) {
    $u                   = Uzivatel::zId($r['id_uzivatele']);
    $r['pozice']         = $u->status(false);
    $r['datum_narozeni'] = $u->datumNarozeni()->format(DateTimeCz::FORMAT_DATUM_STANDARD);
    $r['cislo_dokladu']  = $u->cisloOp();
    $vystup[]            = $r;
}

Report::zPole($vystup)->tFormat(get('format'));
