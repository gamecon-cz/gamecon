<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Quick reports keep their SQL in the database, so a schema change does not reach them. Five read the removed
// columns. Three are rewritten whole, only while they read exactly as shipped; two by fragments, only while every
// fragment is found. An edited one is left alone and found by the check in legacy-catalog-removed.
// Stored reports may end their lines with CRLF, so the comparison ignores CRs and trailing whitespace.

$wholeReports = [
    // seznam aktivit
    46 => [
        <<<'SQL'
select * from shop_nakupy
join shop_predmety ON shop_predmety.id_predmetu = shop_nakupy.id_predmetu AND shop_predmety.typ IN (1, 3)
where rok = 2022 order by datum asc
SQL,
        <<<'SQL'
select shop_nakupy.id_nakupu, shop_nakupy.id_uzivatele, shop_nakupy.id_objednatele, product_variant.product_id AS product_id,
       shop_nakupy.rok, shop_nakupy.cena_nakupni, shop_nakupy.datum, shop_nakupy.order_id, shop_nakupy.product_name,
       shop_nakupy.product_code, shop_nakupy.product_tags, shop_nakupy.product_description, shop_nakupy.original_price,
       shop_nakupy.discount_amount, shop_nakupy.discount_reason, shop_nakupy.variant_id, shop_nakupy.variant_name,
       shop_nakupy.variant_code, shop_nakupy.bundle_id, shop_nakupy.discount_snapshot, shop_nakupy.override_log,
       produkt.nazev, produkt.kod_predmetu, produkt.cena_aktualni,
       produkt.stav, produkt.nabizet_do, vychozi_varianta.capacity AS kusu_vyrobeno,
       produkt.ubytovani_den, produkt.popis, produkt.vedlejsi,
       produkt.archived_at, produkt.breakfast_included,
       produkt.reserved_for_organizers,
       CASE kategorie.code WHEN 'predmet' THEN 1 WHEN 'tricko' THEN 3 END AS typ,
       CASE
           WHEN produkt.breakfast_included THEN 'hotel'
           WHEN EXISTS (
               SELECT 1
               FROM product_product_tag AS stitek_podtypu
               INNER JOIN product_tag AS stitek_mikiny ON stitek_mikiny.id = stitek_podtypu.tag_id
               WHERE stitek_podtypu.product_id = produkt.id_predmetu AND stitek_mikiny.code = 'mikina'
           ) THEN 'mikina'
       END AS podtyp,
       COALESCE(YEAR(produkt.archived_at), {ROCNIK}) AS model_rok,
       IF(produkt.archived_at IS NULL, 1, 0) AS je_letosni_hlavni
from shop_nakupy
inner join product_variant ON product_variant.id = shop_nakupy.variant_id
inner join shop_predmety AS produkt ON produkt.id_predmetu = product_variant.product_id
inner join product_product_tag ON product_product_tag.product_id = produkt.id_predmetu
inner join product_tag AS kategorie ON kategorie.id = product_product_tag.tag_id AND kategorie.code IN ('predmet', 'tricko')
left join product_variant AS vychozi_varianta ON vychozi_varianta.product_id = produkt.id_predmetu AND vychozi_varianta.code = produkt.kod_predmetu
where rok = 2022 order by datum asc, shop_nakupy.id_nakupu
SQL,
    ],
    // po termínu zrušená jídla
    79 => [
        <<<'SQL'
select * from shop_nakupy_zrusene snp
join shop_predmety sp on snp.id_predmetu = sp.id_predmetu
where sp.typ = 4
  and snp.datum_zruseni > '2024-07-14'
SQL,
        <<<'SQL'
select snp.id_nakupu, snp.id_uzivatele, product_variant.product_id AS product_id, snp.rocnik, snp.cena_nakupni, snp.datum_nakupu,
       snp.datum_zruseni, snp.zdroj_zruseni, snp.product_name, snp.product_code, snp.variant_id,
       sp.nazev, sp.kod_predmetu, sp.cena_aktualni, sp.stav, sp.nabizet_do, vychozi_varianta.capacity AS kusu_vyrobeno, sp.ubytovani_den,
       sp.popis, sp.vedlejsi, sp.archived_at, sp.breakfast_included, sp.reserved_for_organizers, 4 AS typ,
       CASE
           WHEN sp.breakfast_included THEN 'hotel'
           WHEN EXISTS (
               SELECT 1
               FROM product_product_tag AS stitek_podtypu
               INNER JOIN product_tag AS stitek_mikiny ON stitek_mikiny.id = stitek_podtypu.tag_id
               WHERE stitek_podtypu.product_id = sp.id_predmetu AND stitek_mikiny.code = 'mikina'
           ) THEN 'mikina'
       END AS podtyp,
       COALESCE(YEAR(sp.archived_at), {ROCNIK}) AS model_rok,
       IF(sp.archived_at IS NULL, 1, 0) AS je_letosni_hlavni
from shop_nakupy_zrusene snp
inner join product_variant ON product_variant.id = snp.variant_id
inner join shop_predmety sp on sp.id_predmetu = product_variant.product_id
inner join product_product_tag ON product_product_tag.product_id = sp.id_predmetu
inner join product_tag AS kategorie ON kategorie.id = product_product_tag.tag_id AND kategorie.code = 'jidlo'
left join product_variant AS vychozi_varianta ON vychozi_varianta.product_id = sp.id_predmetu AND vychozi_varianta.code = sp.kod_predmetu
where snp.datum_zruseni > '2024-07-14'
SQL,
    ],
    // nakupy
    88 => [
        <<<'SQL'
select n.id_uzivatele, n.id_predmetu, p.nazev, n.rok, n.cena_nakupni, n.datum
from shop_nakupy n
join shop_predmety p
on p.id_predmetu = n.id_predmetu
SQL,
        <<<'SQL'
select shop_nakupy.id_uzivatele, product_variant.product_id AS product_id, shop_nakupy.product_name AS nazev, shop_nakupy.variant_name AS varianta,
       shop_nakupy.rok, shop_nakupy.cena_nakupni, shop_nakupy.datum
from shop_nakupy
inner join product_variant ON product_variant.id = shop_nakupy.variant_id
order by shop_nakupy.id_nakupu
SQL,
    ],
];

// These two are long and still edited by hand, so only the parts that read the removed columns are swapped.
$nightJoin = <<<'SQL'
JOIN product_variant AS noc ON noc.id = %s.variant_id
        JOIN shop_predmety AS typ_pokoje ON typ_pokoje.id_predmetu = noc.product_id
        JOIN product_product_tag ON product_product_tag.product_id = typ_pokoje.id_predmetu
        JOIN product_tag ON product_tag.id = product_product_tag.tag_id AND product_tag.code = 'ubytovani'
SQL;

$partialReports = [
    80 => [
        'SELECT shop_nakupy.id_uzivatele, shop_predmety.nazev, shop_predmety.ubytovani_den,'
            => "SELECT shop_nakupy.id_uzivatele, CONCAT_WS(' ', typ_pokoje.nazev, noc.name) AS nazev, noc.accommodation_day AS ubytovani_den,",
        'SELECT shop_nakupy_zrusene.id_uzivatele, shop_predmety.nazev, shop_predmety.ubytovani_den,'
            => "SELECT shop_nakupy_zrusene.id_uzivatele, CONCAT_WS(' ', typ_pokoje.nazev, noc.name) AS nazev, noc.accommodation_day AS ubytovani_den,",
        'JOIN shop_predmety ON shop_predmety.id_predmetu = shop_nakupy.id_predmetu'
            => sprintf($nightJoin, 'shop_nakupy'),
        'JOIN shop_predmety ON shop_predmety.id_predmetu = shop_nakupy_zrusene.id_predmetu'
            => sprintf($nightJoin, 'shop_nakupy_zrusene'),
        "\n          AND shop_predmety.typ = 2" => '',
    ],
    96 => [
        'join shop_predmety sp on sp.id_predmetu = sn.id_predmetu'
            => "join product_variant sp on sp.id = sn.variant_id\nleft join (product_product_tag stitek_kategorie inner join product_tag kategorie on kategorie.id = stitek_kategorie.tag_id and kategorie.code in ('ubytovani', 'jidlo')) on stitek_kategorie.product_id = sp.product_id",
        'sp.ubytovani_den' => 'sp.accommodation_day',
        'sp.typ = 2'       => "kategorie.code = 'ubytovani'",
        'sp.typ = 4'       => "kategorie.code = 'jidlo'",
    ],
];

$normalize = static fn (string $query): string => rtrim(str_replace("\r", '', $query));

foreach ($this->q('SELECT id, dotaz FROM reporty_quick WHERE dotaz LIKE \'%shop\\_%\'')->fetchAll(PDO::FETCH_ASSOC) as $report) {
    $reportId = (int) $report['id'];
    $query = $normalize((string) $report['dotaz']);

    if (isset($wholeReports[$reportId])) {
        [$shipped, $rewritten] = $wholeReports[$reportId];
    } elseif (isset($partialReports[$reportId])) {
        foreach (array_keys($partialReports[$reportId]) as $fragment) {
            if (!str_contains($query, $fragment)) {
                continue 2;
            }
        }
        $shipped = $query;
        $rewritten = strtr($query, $partialReports[$reportId]);
    } else {
        continue;
    }

    if ($query === $normalize($shipped) && $rewritten !== $query) {
        // q() takes no bound parameters
        $this->q('UPDATE reporty_quick SET dotaz = ' . $this->connection->quote($rewritten) . ' WHERE id = ' . $reportId);
    }
}
