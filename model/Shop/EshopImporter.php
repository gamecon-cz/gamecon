<?php

declare(strict_types=1);

namespace Gamecon\Shop;

use OpenSpout\Reader\XLSX\Reader as XLSXReader;

class EshopImporter
{
    public function __construct(
        private readonly string $souborCesta,
    ) {}

    public function importuj(): EshopImportVysledek
    {
        if (!is_readable($this->souborCesta)) {
            throw new \Chyba('Soubor se nepodařilo načíst');
        }

        $reader = new XLSXReader();
        $reader->open($this->souborCesta);

        $reader->getSheetIterator()->rewind();
        /** @var \OpenSpout\Reader\SheetInterface $sheet */
        $sheet = $reader->getSheetIterator()->current();

        $rowIterator = $sheet->getRowIterator();
        $rowIterator->rewind();
        /** @var \OpenSpout\Common\Entity\Row|null $row */
        $row           = $rowIterator->current();
        $hlavickaKlice = array_map('trim', $row->toArray());
        $hlavicka      = array_flip($hlavickaKlice);

        $pozadovaneSloupce = ['nazev', 'kod_predmetu', 'cena_aktualni', 'stav', 'nabizet_do', 'kusu_vyrobeno', 'tag', 'ubytovani_den', 'popis', 'vedlejsi', 'snidane_v_cene'];
        if (!array_keys_exist($pozadovaneSloupce, $hlavicka)) {
            throw new \Chyba('Chybný formát souboru - chybí sloupce ' . implode(',', array_diff($pozadovaneSloupce, array_keys($hlavicka))));
        }

        $indexNazev         = $hlavicka['nazev'];
        $indexKodPredmetu   = $hlavicka['kod_predmetu'];
        $indexCenaAktualni  = $hlavicka['cena_aktualni'];
        $indexStav          = $hlavicka['stav'];
        $indexNabizetDo     = $hlavicka['nabizet_do'];
        $indexKusuVyrobeno  = $hlavicka['kusu_vyrobeno'];
        $indexTag           = $hlavicka['tag'];
        $indexUbytovaniDen  = $hlavicka['ubytovani_den'];
        $indexPopis         = $hlavicka['popis'];
        $indexVedlejsi      = $hlavicka['vedlejsi'];
        $indexSnidaneVCene  = $hlavicka['snidane_v_cene'];

        $rowIterator->next();

        $cisloNeboNull = static fn(
            $hodnota,
        ) => trim((string) $hodnota) !== ''
            ? $hodnota
            : null;

        $hodnotaNeboKodZNazvu = static fn(
            $hodnota,
            string $nazev,
        ) => trim((string) $hodnota) !== ''
            ? $hodnota
            : kodZNazvu($nazev);

        $trimRadek = static fn(
            array $radek,
        ) => array_map(
            static fn(
                $hodnota,
            ) => is_string($hodnota)
                ? trim($hodnota)
                : $hodnota,
            $radek,
        );

        $stringNullJakoNullRadek = static fn(
            array $radek,
        ) => array_map(
            static fn(
                $hodnota,
            ) => is_string($hodnota) && strtoupper($hodnota) === 'NULL'
                ? null
                : $hodnota,
            $radek,
        );

        $chyby             = [];
        $sqlValuesArray     = [];
        $tagsByKodPredmetu  = [];
        $poradiRadku        = 1;
        /** @var \OpenSpout\Common\Entity\Row|null $row */
        while ($rowIterator->valid()) {
            $radek = $rowIterator->current()->toArray();
            $poradiRadku++;
            $rowIterator->next();

            if ($radek) {
                $radek       = $trimRadek($radek);
                $radek       = $stringNullJakoNullRadek($radek);
                $kodPredmetu = $hodnotaNeboKodZNazvu(
                    $radek[$indexKodPredmetu],
                    (string) $radek[$indexNazev],
                );
                $tag = trim((string) ($radek[$indexTag] ?? ''));
                if ($tag === '') {
                    $chyby[] = sprintf(
                        'Na řádku %d chybí tag v %d. sloupci',
                        $poradiRadku,
                        $indexTag + 1,
                    );
                    continue;
                }
                $tagsByKodPredmetu[$kodPredmetu] = $tag;

                $sqlValuesArray[] = '(' . dbQa([
                        $radek[$indexNazev],
                        $kodPredmetu,
                        $radek[$indexCenaAktualni],
                        $radek[$indexStav],
                        $radek[$indexNabizetDo],
                        $cisloNeboNull($radek[$indexKusuVyrobeno]),
                        $cisloNeboNull($radek[$indexUbytovaniDen]),
                        $radek[$indexPopis],
                        (int) ((string) ($radek[$indexVedlejsi] ?? 0)),
                        (int) (bool) ($radek[$indexSnidaneVCene] ?? false),
                    ]) . ')';
            }
        }
        $reader->close();

        if ($chyby) {
            throw new \Chyba('Chybička se vloudila: ' . implode("; ", $chyby));
        }

        $pocetZmenenych  = 0;
        $pocetNovych     = 0;
        $pocetVyrazenych = 0;

        if ($sqlValuesArray) {
            $temporaryTable = uniqid('import_eshopu_tmp_', true);
            dbQuery(<<<SQL
CREATE TEMPORARY TABLE `$temporaryTable` (
    `nazev` VARCHAR(255) NOT NULL,
    `kod_predmetu` VARCHAR(255) NOT NULL,
    `cena_aktualni` DECIMAL(6,2) NOT NULL DEFAULT 0,
    `stav` SMALLINT NOT NULL DEFAULT 0,
    `nabizet_do` DATETIME DEFAULT NULL,
    `kusu_vyrobeno` SMALLINT DEFAULT NULL,
    `ubytovani_den` SMALLINT DEFAULT NULL,
    `popis` VARCHAR(2000) NOT NULL DEFAULT '',
    `vedlejsi` TINYINT(1) NOT NULL DEFAULT 0,
    `snidane_v_cene` TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY (`kod_predmetu`)
)
SQL,
            );

            $sqlValues = implode(",\n", $sqlValuesArray);

            dbQuery(<<<SQL
INSERT INTO `$temporaryTable` (`nazev`, `kod_predmetu`, `cena_aktualni`, `stav`, `nabizet_do`, `kusu_vyrobeno`, `ubytovani_den`, `popis`, `vedlejsi`, `snidane_v_cene`)
    VALUES
$sqlValues
SQL,
            );

            // Update existing products (matched by kod_predmetu)
            $mysqliResult = dbQuery(<<<SQL
UPDATE shop_predmety
JOIN `$temporaryTable` AS import
    ON shop_predmety.kod_predmetu = import.kod_predmetu
SET
    shop_predmety.nazev = import.nazev,
    shop_predmety.cena_aktualni = import.cena_aktualni,
    shop_predmety.stav = import.stav,
    shop_predmety.nabizet_do = import.nabizet_do,
    shop_predmety.kusu_vyrobeno = import.kusu_vyrobeno,
    shop_predmety.ubytovani_den = import.ubytovani_den,
    shop_predmety.popis = import.popis,
    shop_predmety.vedlejsi = import.vedlejsi,
    shop_predmety.breakfast_included = import.snidane_v_cene,
    shop_predmety.archived_at = NULL
WHERE TRUE
SQL,
            );
            $pocetZmenenych = dbAffectedOrNumRows($mysqliResult);

            // Insert new products
            $mysqliResult = dbQuery(<<<SQL
INSERT INTO shop_predmety (`nazev`, `kod_predmetu`, `cena_aktualni`, `stav`,  `nabizet_do`, `kusu_vyrobeno`, `ubytovani_den`, `popis`, `vedlejsi`, `breakfast_included`)
SELECT
    import.`nazev`,
    import.`kod_predmetu`,
    import.`cena_aktualni`,
    import.`stav`,
    import.`nabizet_do`,
    import.`kusu_vyrobeno`,
    import.`ubytovani_den`,
    import.`popis`,
    import.`vedlejsi`,
    import.`snidane_v_cene`
FROM `$temporaryTable` AS import
LEFT JOIN shop_predmety AS uz_zname
    ON uz_zname.kod_predmetu = import.kod_predmetu
WHERE uz_zname.id_predmetu IS NULL
SQL,
            );
            $pocetNovych = dbAffectedOrNumRows($mysqliResult);

            // Every product is addressable by the cart through a variant, so a product without one
            // is invisible to it. Give each imported product the default variant mirroring itself,
            // the same shape the backfill migration created for legacy rows. Restricted to the
            // imported codes, so a product whose variants were deliberately removed elsewhere does
            // not get one resurrected here.
            dbQuery(<<<SQL
INSERT INTO product_variant (product_id, name, code, price, remaining_quantity, reserved_for_organizers, accommodation_day, position)
SELECT shop_predmety.id_predmetu,
       shop_predmety.nazev,
       shop_predmety.kod_predmetu,
       NULL,
       shop_predmety.kusu_vyrobeno,
       NULL,
       shop_predmety.ubytovani_den,
       0
FROM shop_predmety
JOIN `$temporaryTable` AS import ON import.kod_predmetu = shop_predmety.kod_predmetu
WHERE NOT EXISTS (
    SELECT 1 FROM product_variant WHERE product_variant.product_id = shop_predmety.id_predmetu
)
  AND NOT EXISTS (
    -- code is UNIQUE across all variants; colliding would abort the whole import
    SELECT 1 FROM product_variant AS jine WHERE jine.code = shop_predmety.kod_predmetu
)
SQL,
            );

            // A re-import may rename a product or change its stock; the default variant mirrors it,
            // so it has to follow, otherwise the cart keeps showing (and snapshotting) the old label.
            dbQuery(<<<SQL
UPDATE product_variant
JOIN shop_predmety ON shop_predmety.id_predmetu = product_variant.product_id
JOIN `$temporaryTable` AS import ON import.kod_predmetu = shop_predmety.kod_predmetu
SET product_variant.name = shop_predmety.nazev,
    product_variant.remaining_quantity = shop_predmety.kusu_vyrobeno,
    product_variant.accommodation_day = shop_predmety.ubytovani_den
WHERE product_variant.code = shop_predmety.kod_predmetu
SQL,
            );

            // Sync tags for all imported products (new and updated)
            foreach ($tagsByKodPredmetu as $kodPredmetu => $tagCode) {
                $idPredmetu = dbOneCol(
                    'SELECT id_predmetu FROM shop_predmety WHERE kod_predmetu = $0',
                    [0 => $kodPredmetu],
                );
                if ($idPredmetu === null) {
                    continue;
                }
                // Remove old category tags and set the new one
                dbQuery(<<<SQL
DELETE product_product_tag FROM product_product_tag
JOIN product_tag ON product_product_tag.tag_id = product_tag.id
WHERE product_product_tag.product_id = $0
  AND product_tag.code IN ('predmet','ubytovani','tricko','jidlo','vstupne','parcon','proplaceni-bonusu')
SQL,
                    [0 => $idPredmetu],
                );
                dbQuery(<<<SQL
INSERT INTO product_product_tag (product_id, tag_id)
SELECT $0, id FROM product_tag WHERE code = $1
SQL,
                    [0 => $idPredmetu, 1 => $tagCode],
                );
            }

            // Archive products not in the import file
            $mysqliResult = dbQuery(<<<SQL
UPDATE shop_predmety AS stare
LEFT JOIN `$temporaryTable` AS import
    ON stare.kod_predmetu = import.kod_predmetu
SET stare.archived_at = NOW()
WHERE import.kod_predmetu IS NULL
  AND stare.archived_at IS NULL
SQL,
            );
            $pocetVyrazenych = dbAffectedOrNumRows($mysqliResult);

            dbQuery(<<<SQL
DROP TEMPORARY TABLE `$temporaryTable`
SQL,
            );
        }

        return new EshopImportVysledek($pocetNovych, $pocetZmenenych, $pocetVyrazenych);
    }
}
