<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Legacy code reads the catalog from shop_predmety, product_variant and the tags now, so the
// compatibility views go. Quick reports keep their SQL in the database, so the four that still
// read a view are rewritten to the tables first, with the same columns and rows.
$celeDotazy = [
    <<<'SQL'
select shop_nakupy.id_nakupu, shop_nakupy.id_uzivatele, shop_nakupy.id_objednatele, shop_nakupy.id_predmetu AS product_id,
       shop_nakupy.rok, shop_nakupy.cena_nakupni, shop_nakupy.datum, shop_nakupy.order_id, shop_nakupy.product_name,
       shop_nakupy.product_code, shop_nakupy.product_tags, shop_nakupy.product_description, shop_nakupy.original_price,
       shop_nakupy.discount_amount, shop_nakupy.discount_reason, shop_nakupy.variant_id, shop_nakupy.variant_name,
       shop_nakupy.variant_code, shop_nakupy.bundle_id, shop_nakupy.discount_snapshot, shop_nakupy.override_log,
       shop_predmety_s_typem.nazev, shop_predmety_s_typem.kod_predmetu, shop_predmety_s_typem.cena_aktualni,
       shop_predmety_s_typem.stav, shop_predmety_s_typem.nabizet_do, shop_predmety_s_typem.kusu_vyrobeno,
       shop_predmety_s_typem.ubytovani_den, shop_predmety_s_typem.popis, shop_predmety_s_typem.vedlejsi,
       shop_predmety_s_typem.archived_at, shop_predmety_s_typem.breakfast_included,
       shop_predmety_s_typem.reserved_for_organizers, shop_predmety_s_typem.typ, shop_predmety_s_typem.podtyp,
       shop_predmety_s_typem.model_rok, shop_predmety_s_typem.je_letosni_hlavni
from shop_nakupy
join shop_predmety_s_typem ON shop_predmety_s_typem.id_predmetu = shop_nakupy.id_predmetu AND shop_predmety_s_typem.typ IN (1, 3)
where rok = 2022 order by datum asc
SQL => <<<'SQL'
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
    <<<'SQL'
select snp.id_nakupu, snp.id_uzivatele, snp.id_predmetu AS product_id, snp.rocnik, snp.cena_nakupni, snp.datum_nakupu,
       snp.datum_zruseni, snp.zdroj_zruseni, snp.product_name, snp.product_code, snp.variant_id,
       sp.nazev, sp.kod_predmetu, sp.cena_aktualni, sp.stav, sp.nabizet_do, sp.kusu_vyrobeno, sp.ubytovani_den,
       sp.popis, sp.vedlejsi, sp.archived_at, sp.breakfast_included, sp.reserved_for_organizers, sp.typ, sp.podtyp,
       sp.model_rok, sp.je_letosni_hlavni
from shop_nakupy_zrusene snp
join shop_predmety_s_typem sp on snp.id_predmetu = sp.id_predmetu
where sp.typ = 4
  and snp.datum_zruseni > '2024-07-14'
SQL => <<<'SQL'
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
];

// These two are long and still edited by hand, so only the parts that read a view are swapped.
$noc = <<<'SQL'
JOIN product_variant AS noc ON noc.id = %s.variant_id
        JOIN shop_predmety AS typ_pokoje ON typ_pokoje.id_predmetu = noc.product_id
        JOIN product_product_tag ON product_product_tag.product_id = typ_pokoje.id_predmetu
        JOIN product_tag ON product_tag.id = product_product_tag.tag_id AND product_tag.code = 'ubytovani'
SQL;
$castiDotazu = [
    80 => [
        'SELECT shop_nakupy.id_uzivatele, shop_predmety_s_typem.nazev, shop_predmety_s_typem.ubytovani_den,'
            => "SELECT shop_nakupy.id_uzivatele, CONCAT_WS(' ', typ_pokoje.nazev, noc.name) AS nazev, noc.accommodation_day AS ubytovani_den,",
        'SELECT shop_nakupy_zrusene.id_uzivatele, shop_predmety_s_typem.nazev, shop_predmety_s_typem.ubytovani_den,'
            => "SELECT shop_nakupy_zrusene.id_uzivatele, CONCAT_WS(' ', typ_pokoje.nazev, noc.name) AS nazev, noc.accommodation_day AS ubytovani_den,",
        'JOIN shop_varianty_s_typem AS shop_predmety_s_typem ON shop_predmety_s_typem.id_varianty = shop_nakupy.variant_id'
            => sprintf($noc, 'shop_nakupy'),
        'JOIN shop_varianty_s_typem AS shop_predmety_s_typem ON shop_predmety_s_typem.id_varianty = shop_nakupy_zrusene.variant_id'
            => sprintf($noc, 'shop_nakupy_zrusene'),
        "\n          AND shop_predmety_s_typem.typ = 2" => '',
    ],
    96 => [
        'join shop_varianty_s_typem sp on sp.id_varianty = sn.variant_id'
            => "join product_variant sp on sp.id = sn.variant_id\nleft join (product_product_tag stitek_kategorie inner join product_tag kategorie on kategorie.id = stitek_kategorie.tag_id and kategorie.code in ('ubytovani', 'jidlo')) on stitek_kategorie.product_id = sp.product_id",
        'sp.ubytovani_den' => 'sp.accommodation_day',
        'sp.typ = 2'       => "kategorie.code = 'ubytovani'",
        'sp.typ = 4'       => "kategorie.code = 'jidlo'",
    ],
];

$ctiPohled = static fn (string $dotaz): bool => preg_match('/\bshop_(predmety|varianty)_s_typem\b/i', $dotaz) === 1;
$neprepsane = [];
foreach ($this->q("SELECT id, nazev, dotaz FROM reporty_quick WHERE dotaz LIKE '%\\_s\\_typem%'")->fetchAll(\PDO::FETCH_ASSOC) as $report) {
    // A report saved through the admin form may end its lines with CRLF.
    $dotaz = rtrim(str_replace("\r", '', (string) $report['dotaz']));
    $novy = $celeDotazy[$dotaz] ?? strtr($dotaz, $castiDotazu[(int) $report['id']] ?? []);
    if ($ctiPohled($novy)) {
        $neprepsane[] = "{$report['id']} ({$report['nazev']})";
        continue;
    }
    $this->q('UPDATE reporty_quick SET dotaz = ' . $this->connection->quote($novy) . ' WHERE id = ' . (int) $report['id']);
}
// Dropping the views would leave such a report failing on every run, unnoticed until someone opens it.
if ($neprepsane !== []) {
    throw new \RuntimeException('Quick reporty ' . implode(', ', $neprepsane) . ' čtou pohled katalogu a migrace je neumí přepsat — přepiš je na tabulky a migraci spusť znovu.');
}

$this->q('DROP VIEW IF EXISTS shop_varianty_s_typem');
$this->q('DROP VIEW IF EXISTS shop_predmety_s_typem');
