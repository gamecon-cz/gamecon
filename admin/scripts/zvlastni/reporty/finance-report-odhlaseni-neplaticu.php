<?php declare(strict_types=1);

use App\Enum\ProductTagCode;

require_once __DIR__ . '/sdilene-hlavicky.php';

/**
 * Objednávky (aktivity, předměty, ubytování) zrušené kvůli dluhu.
 *
 * Zdroj dat nejsou souhrnné logy (hromadne_akce_log je jen počet lidí za běh),
 * ale detailní záznamy zrušení, které nesou "zdroj zrušení":
 *   - předměty a ubytování: shop_nakupy_zrusene.zdroj_zruseni
 *   - aktivity:             akce_prihlaseni_log.zdroj_zmeny (typ 'odhlaseni')
 *
 * Hromadné odhlašování neplatičů razí zdroj "<zaklad>-<poradi>" – automatický
 * cron používá základ 'automaticky', ruční tlačítko v adminu 'rucne-hromadne'.
 * Filtrujeme tedy na 'automaticky-%' a 'rucne-hromadne', což jsou právě
 * odhlášení kvůli dluhu (na rozdíl od 'rucne-sam-sebe' apod.).
 */

$formatReportu = get('format');
$jeHtml        = $formatReportu === 'html';

$zdrojeDluh = "zdroj = 'rucne-hromadne' OR zdroj LIKE 'automaticky-%'";

$data = dbFetchAll(<<<SQL
    SELECT
        zruseni.rocnik                                    AS rocnik,
        zruseni.id_uzivatele                              AS id_uzivatele,
        CONCAT(uzivatele_hodnoty.jmeno_uzivatele, ' ', uzivatele_hodnoty.prijmeni_uzivatele,
               CASE WHEN uzivatele_hodnoty.login_uzivatele <> ''
                    THEN CONCAT(' (', uzivatele_hodnoty.login_uzivatele, ')') ELSE '' END) AS ucastnik,
        uzivatele_hodnoty.email1_uzivatele                AS email,
        zruseni.je_aktivita                               AS je_aktivita,
        zruseni.kategorie_shop                            AS kategorie_shop,
        zruseni.nazev_polozky                             AS nazev_polozky,
        zruseni.cena                                      AS cena,
        zruseni.objednano_kdy                             AS objednano_kdy,
        zruseni.zruseno_kdy                               AS zruseno_kdy,
        zruseni.zdroj                                     AS zdroj_zruseni
    FROM (
        SELECT
            shop_nakupy_zrusene.rocnik           AS rocnik,
            shop_nakupy_zrusene.id_uzivatele     AS id_uzivatele,
            -- Název ze zrušeného nákupu, ne z produktu: report vypisuje i starší ročníky
            -- a produkt se mezitím mohl přejmenovat. Typ snapshot nenese, ten zůstává
            -- z produktu.
            shop_nakupy_zrusene.product_name     AS nazev_polozky,
            kategorie.code                       AS kategorie_shop,
            NULL                                 AS je_aktivita,
            shop_nakupy_zrusene.cena_nakupni     AS cena,
            shop_nakupy_zrusene.datum_nakupu     AS objednano_kdy,
            shop_nakupy_zrusene.datum_zruseni    AS zruseno_kdy,
            shop_nakupy_zrusene.zdroj_zruseni    AS zdroj
        FROM shop_nakupy_zrusene
        JOIN product_variant ON product_variant.id = shop_nakupy_zrusene.variant_id
        -- a product missing its category keeps its row (typ NULL) instead of vanishing
        LEFT JOIN (
            product_product_tag AS stitek_kategorie
            INNER JOIN product_tag AS kategorie ON kategorie.id = stitek_kategorie.tag_id AND kategorie.code IN ($0)
        ) ON stitek_kategorie.product_id = product_variant.product_id
        WHERE shop_nakupy_zrusene.zdroj_zruseni = 'rucne-hromadne'
           OR shop_nakupy_zrusene.zdroj_zruseni LIKE 'automaticky-%'

        UNION ALL

        SELECT
            akce_prihlaseni_log.rocnik           AS rocnik,
            akce_prihlaseni_log.id_uzivatele     AS id_uzivatele,
            akce_seznam.nazev_akce               AS nazev_polozky,
            NULL                                 AS kategorie_shop,
            1                                    AS je_aktivita,
            NULL                                 AS cena,
            (
                SELECT MAX(prihlaseni_log.kdy)
                FROM akce_prihlaseni_log AS prihlaseni_log
                WHERE prihlaseni_log.id_akce = akce_prihlaseni_log.id_akce
                  AND prihlaseni_log.id_uzivatele = akce_prihlaseni_log.id_uzivatele
                  AND prihlaseni_log.rocnik = akce_prihlaseni_log.rocnik
                  AND prihlaseni_log.typ IN ('prihlaseni', 'prihlaseni_nahradnik')
                  AND prihlaseni_log.kdy <= akce_prihlaseni_log.kdy
            )                                    AS objednano_kdy,
            akce_prihlaseni_log.kdy              AS zruseno_kdy,
            akce_prihlaseni_log.zdroj_zmeny      AS zdroj
        FROM akce_prihlaseni_log
        JOIN akce_seznam ON akce_seznam.id_akce = akce_prihlaseni_log.id_akce
        WHERE akce_prihlaseni_log.typ = 'odhlaseni'
          AND (akce_prihlaseni_log.zdroj_zmeny = 'rucne-hromadne'
               OR akce_prihlaseni_log.zdroj_zmeny LIKE 'automaticky-%')
    ) AS zruseni
    JOIN uzivatele_hodnoty ON uzivatele_hodnoty.id_uzivatele = zruseni.id_uzivatele
    ORDER BY zruseni.zruseno_kdy DESC, zruseni.id_uzivatele, nazev_polozky
    SQL,
    [0 => array_map(static fn (ProductTagCode $kategorie): string => $kategorie->value, ProductTagCode::categories())],
);

$radky = [];
foreach ($data as $radek) {
    $typPolozky = $radek['je_aktivita']
        ? 'aktivita'
        : mb_strtolower(ProductTagCode::tryFrom((string) $radek['kategorie_shop'])?->label() ?? 'neznámý typ');

    $urlUzivatele = URL_ADMIN . '/uzivatel?pracovni_uzivatel=' . $radek['id_uzivatele'];

    $radky[] = [
        'rocnik'         => (int)$radek['rocnik'],
        'ucastnik'       => $jeHtml
            ? "<a target='_blank' href='$urlUzivatele'>{$radek['ucastnik']}</a>"
            : $radek['ucastnik'],
        'email'          => $jeHtml && $radek['email']
            ? "<a href='mailto:{$radek['email']}'>{$radek['email']}</a>"
            : $radek['email'],
        'typ_polozky'    => $typPolozky,
        'nazev_polozky'  => $radek['nazev_polozky'],
        'cena'           => $radek['cena'] !== null ? (float)$radek['cena'] : '',
        'objednano_kdy'  => $radek['objednano_kdy'],
        'zruseno_kdy'    => $radek['zruseno_kdy'],
        'zdroj_zruseni'  => $radek['zdroj_zruseni'],
    ];
}

$report = $radky
    ? Report::zPole($radky)
    : Report::zPoli(
        ['rocnik', 'ucastnik', 'email', 'typ_polozky', 'nazev_polozky', 'cena', 'objednano_kdy', 'zruseno_kdy', 'zdroj_zruseni'],
        [],
    );

$report->tFormat(
    $formatReportu,
    'odhlaseni-neplaticu-' . $report->nazevReportuZRequestu(),
);
