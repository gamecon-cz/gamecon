<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// A purchase's `id_predmetu` used to name the bought size or night and now names its product, so
// quick reports show it as `product_id`. Each is rewritten only while it reads as shipped.
$prepisy = [
    <<<'SQL'
select * from shop_nakupy
join shop_predmety_s_typem ON shop_predmety_s_typem.id_predmetu = shop_nakupy.id_predmetu AND shop_predmety_s_typem.typ IN (1, 3)
where rok = 2022 order by datum asc
SQL => <<<'SQL'
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
SQL,
    <<<'SQL'
select * from shop_nakupy_zrusene snp
join shop_predmety_s_typem sp on snp.id_predmetu = sp.id_predmetu
where sp.typ = 4
  and snp.datum_zruseni > '2024-07-14'

SQL => <<<'SQL'
select snp.id_nakupu, snp.id_uzivatele, snp.id_predmetu AS product_id, snp.rocnik, snp.cena_nakupni, snp.datum_nakupu,
       snp.datum_zruseni, snp.zdroj_zruseni, snp.product_name, snp.product_code, snp.variant_id,
       sp.nazev, sp.kod_predmetu, sp.cena_aktualni, sp.stav, sp.nabizet_do, sp.kusu_vyrobeno, sp.ubytovani_den,
       sp.popis, sp.vedlejsi, sp.archived_at, sp.breakfast_included, sp.reserved_for_organizers, sp.typ, sp.podtyp,
       sp.model_rok, sp.je_letosni_hlavni
from shop_nakupy_zrusene snp
join shop_predmety_s_typem sp on snp.id_predmetu = sp.id_predmetu
where sp.typ = 4
  and snp.datum_zruseni > '2024-07-14'
SQL,
    <<<'SQL'
select shop_nakupy.id_uzivatele, shop_nakupy.id_predmetu, shop_nakupy.product_name AS nazev, shop_nakupy.variant_name AS varianta,
       shop_nakupy.rok, shop_nakupy.cena_nakupni, shop_nakupy.datum
from shop_nakupy
SQL => <<<'SQL'
select shop_nakupy.id_uzivatele, shop_nakupy.id_predmetu AS product_id, shop_nakupy.product_name AS nazev, shop_nakupy.variant_name AS varianta,
       shop_nakupy.rok, shop_nakupy.cena_nakupni, shop_nakupy.datum
from shop_nakupy
SQL,
];

// Stored reports may end their lines with CRLF, so CRs are ignored.
// `q()` takes no bound parameters, and the stored SQL is quoted through PDO.
foreach ($prepisy as $puvodniDotaz => $novyDotaz) {
    $this->q(
        'UPDATE reporty_quick SET dotaz = ' . $this->connection->quote($novyDotaz)
        . ' WHERE REPLACE(dotaz, CHAR(13), \'\') = ' . $this->connection->quote($puvodniDotaz),
    );
}
