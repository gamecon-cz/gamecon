<?php

use Gamecon\XTemplate\XTemplate;
use OpenSpout\Reader\Common\Creator\ReaderFactory;

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$postName        = 'importEshopu';
$souborInputName = 'eshopSoubor';

if (!post($postName)) {
    $importTemplate = new XTemplate(__DIR__ . '/_import-eshopu.xtpl');
    $importTemplate->assign(
        'eshopReport',
        basename(__DIR__ . '/../zvlastni/reporty/finance-report-eshop.php', '.php'),
    );
    $importTemplate->assign('postName', $postName);
    $importTemplate->assign('souborInputName', $souborInputName);
    $importTemplate->assign('baseUrl', URL_ADMIN);

    $importTemplate->parse('import');
    $importTemplate->out('import');

    return;
}

$vstupniSoubor = $_FILES[$souborInputName]['tmp_name'] ?? '';

if (!is_readable($vstupniSoubor)) {
    throw new Chyba('Soubor se nepodařilo načíst');
}

$reader = ReaderFactory::createFromFileByMimeType($vstupniSoubor);
$reader->open($vstupniSoubor);

$reader->getSheetIterator()->rewind();
/** @var \OpenSpout\Reader\SheetInterface $sheet */
$sheet = $reader->getSheetIterator()->current();

$rowIterator = $sheet->getRowIterator();
$rowIterator->rewind();
/** @var \OpenSpout\Common\Entity\Row|null $hlavicka */
$row      = $rowIterator->current();
$hlavicka = array_flip($row->toArray());

$pozadovaneSloupce = ['model_rok', 'nazev', 'cena_aktualni', 'stav', 'auto', 'nabizet_do', 'kusu_vyrobeno', 'typ', 'ubytovani_den', 'popis'];
if (!array_keys_exist($pozadovaneSloupce, $hlavicka)) {
    throw new Chyba('Chybný formát souboru - chybí sloupce ' . implode(',', array_diff($pozadovaneSloupce, array_keys($hlavicka))));
}

$indexModelRok     = $hlavicka['model_rok'];
$indexNazev        = $hlavicka['nazev'];
$indexCenaAktualni = $hlavicka['cena_aktualni'];
$indexStav         = $hlavicka['stav'];
$indexAuto         = $hlavicka['auto'];
$indexNabizetDo    = $hlavicka['nabizet_do'];
$indexKusuVyrobeno = $hlavicka['kusu_vyrobeno'];
$indexTyp          = $hlavicka['typ'];
$indexUbytovaniDen = $hlavicka['ubytovani_den'];
$indexPopis        = $hlavicka['popis'];

$rowIterator->next();

$cisloNeboNull = static fn($hodnota) => trim((string)$hodnota) !== ''
    ? $hodnota
    : null;

$chyby          = [];
$varovani       = [];
$sqlValuesArray = [];
$poradiRadku    = 1;
/** @var \OpenSpout\Common\Entity\Row|null $row */
while ($rowIterator->valid()) {
    $radek = $rowIterator->current()->toArray();
    $poradiRadku++;
    $rowIterator->next();

    if ($radek) {
        $modelRok = (int)($radek[$indexModelRok] ?? null);
        if (!$modelRok) {
            $chyby[] = sprintf(
                'Na řádku %d chybí rok v %d. sloupci',
                $poradiRadku,
                $indexModelRok + 1,
            );
            continue;
        }

        if ($modelRok !== $systemoveNastaveni->rok()) {
            $varovani[] = sprintf(
                'Řádek %d je pro rok %d a byl přeskočen.',
                $poradiRadku,
                $modelRok,
            );
            continue;
        }

        $sqlValuesArray[] = '(' . dbQa([
                $radek[$indexModelRok],
                $radek[$indexNazev],
                $radek[$indexCenaAktualni],
                $radek[$indexStav],
                $radek[$indexAuto],
                $radek[$indexNabizetDo],
                $cisloNeboNull($radek[$indexKusuVyrobeno]),
                $cisloNeboNull($radek[$indexTyp]),
                $cisloNeboNull($radek[$indexUbytovaniDen]),
                $radek[$indexPopis],
            ]) . ')';
    }
}
$reader->close();

if ($chyby) {
    throw new Chyba('Chybička se vloudila: ' . implode("; ", $chyby));
}

if ($varovani) {
    varovani('Drobnosti: ' . implode(',', $varovani), false);
}

$pocetZmenenych = 0;
$pocetNovych    = 0;
if ($sqlValuesArray) {
    $temporaryTable = uniqid('import_eshopu_tmp_', true);
    dbQuery(<<<SQL
CREATE TEMPORARY TABLE `$temporaryTable`
LIKE shop_predmety
SQL,
    );

    $sqlValues = implode(",\n", $sqlValuesArray);

    dbQuery(<<<SQL
INSERT INTO `$temporaryTable` (`model_rok`, `nazev`, `cena_aktualni`, `stav`, `auto`, `nabizet_do`, `kusu_vyrobeno`, `typ`, `ubytovani_den`, `popis`)
    VALUES
$sqlValues
SQL,
    );

    $mysqliResult   = dbQuery(<<<SQL
UPDATE shop_predmety
JOIN `$temporaryTable`
    ON shop_predmety.nazev = `$temporaryTable`.nazev
    AND shop_predmety.model_rok = `$temporaryTable`.model_rok
SET shop_predmety.cena_aktualni = `$temporaryTable`.cena_aktualni,
    shop_predmety.stav = `$temporaryTable`.stav,
    shop_predmety.auto = `$temporaryTable`.auto,
    shop_predmety.nabizet_do = `$temporaryTable`.nabizet_do,
    shop_predmety.kusu_vyrobeno = `$temporaryTable`.kusu_vyrobeno,
    shop_predmety.typ = `$temporaryTable`.typ,
    shop_predmety.ubytovani_den = `$temporaryTable`.ubytovani_den,
    shop_predmety.popis = `$temporaryTable`.popis
WHERE TRUE -- už vyřešeno přes INNER JOIN a unique key
SQL,
    );
    $pocetZmenenych = dbNumRows($mysqliResult);

    $mysqliResult = dbQuery(<<<SQL
INSERT INTO shop_predmety (`model_rok`, `nazev`, `cena_aktualni`, `stav`, `auto`, `nabizet_do`, `kusu_vyrobeno`, `typ`, `ubytovani_den`, `popis`)
SELECT zdroj.`model_rok`,
    zdroj.`nazev`,
    zdroj.`cena_aktualni`,
    zdroj.`stav`,
    zdroj.`auto`,
    zdroj.`nabizet_do`,
    zdroj.`kusu_vyrobeno`,
    zdroj.`typ`,
    zdroj.`ubytovani_den`,
    zdroj.`popis`
FROM `$temporaryTable` AS zdroj
LEFT JOIN shop_predmety AS uz_zname
    ON uz_zname.nazev = zdroj.nazev
    AND uz_zname.model_rok = zdroj.model_rok
WHERE uz_zname.id_predmetu IS NULL -- LEFT JOIN takže NULL je tam, kde jsme záznam přes ON podmínky nenašli
SQL,
    );
    $pocetNovych  = dbNumRows($mysqliResult);

    dbQuery(<<<SQL
DROP TEMPORARY TABLE `$temporaryTable`
SQL,
    );
}

oznameni("Import dokončen. Přidáno {$pocetNovych} nových položek, upraveno {$pocetZmenenych} stávajících.");
