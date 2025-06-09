<?php

use Gamecon\XTemplate\XTemplate;
use Gamecon\Shop\StavPredmetu;
use OpenSpout\Reader\XLSX\Reader as XLSXReader;

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

$reader = new XLSXReader();
$reader->open($vstupniSoubor);

$reader->getSheetIterator()->rewind();
/** @var \OpenSpout\Reader\SheetInterface $sheet */
$sheet = $reader->getSheetIterator()->current();

$rowIterator = $sheet->getRowIterator();
$rowIterator->rewind();
/** @var \OpenSpout\Common\Entity\Row|null $hlavicka */
$row           = $rowIterator->current();
$hlavickaKlice = array_map('trim', $row->toArray());
$hlavicka      = array_flip($hlavickaKlice);

$pozadovaneSloupce = ['model_rok', 'nazev', 'kod_predmetu', 'cena_aktualni', 'stav', 'nabizet_do', 'kusu_vyrobeno', 'typ', 'je_letosni_hlavni', 'ubytovani_den', 'popis'];
if (!array_keys_exist($pozadovaneSloupce, $hlavicka)) {
    throw new Chyba('Chybný formát souboru - chybí sloupce ' . implode(',', array_diff($pozadovaneSloupce, array_keys($hlavicka))));
}

$indexModelRok        = $hlavicka['model_rok'];
$indexNazev           = $hlavicka['nazev'];
$indexKodPredmetu     = $hlavicka['kod_predmetu'];
$indexCenaAktualni    = $hlavicka['cena_aktualni'];
$indexStav            = $hlavicka['stav'];
$indexNabizetDo       = $hlavicka['nabizet_do'];
$indexKusuVyrobeno    = $hlavicka['kusu_vyrobeno'];
$indexTyp             = $hlavicka['typ'];
$indexJeLetosniHlavni = $hlavicka['je_letosni_hlavni'];
$indexUbytovaniDen    = $hlavicka['ubytovani_den'];
$indexPopis           = $hlavicka['popis'];

$rowIterator->next();

$cisloNeboNull = static fn(
    $hodnota,
) => trim((string)$hodnota) !== ''
    ? $hodnota
    : null;

$celeCislo = static fn(
    $hodnota,
) => (int)((string)$hodnota);

$hodnotaNeboKodZNazvu = static fn(
    $hodnota,
    string $nazev,
) => trim((string)$hodnota) !== ''
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

$kodyPredmetu   = [];
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
        $radek    = $trimRadek($radek);
        $radek    = $stringNullJakoNullRadek($radek);
        $modelRok = (int)($radek[$indexModelRok] ?? null);
        if (!$modelRok) {
            $chyby[] = sprintf(
                'Na řádku %d chybí rok v %d. sloupci',
                $poradiRadku,
                $indexModelRok + 1,
            );
            continue;
        }
        $sqlValuesArray[] = '(' . dbQa([
                $radek[$indexModelRok],
                $radek[$indexNazev],
                $hodnotaNeboKodZNazvu(
                    $radek[$indexKodPredmetu],
                    $radek[$indexNazev],
                ),
                $radek[$indexCenaAktualni],
                $radek[$indexStav],
                $radek[$indexNabizetDo],
                $cisloNeboNull($radek[$indexKusuVyrobeno]),
                $cisloNeboNull($radek[$indexTyp]),
                $celeCislo($radek[$indexJeLetosniHlavni]),
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
INSERT INTO `$temporaryTable` (`model_rok`, `nazev`, `kod_predmetu`, `cena_aktualni`, `stav`, `nabizet_do`, `kusu_vyrobeno`, `typ`, `ubytovani_den`, `popis`)
    VALUES
$sqlValues
SQL,
    );

    $mysqliResult   = dbQuery(<<<SQL
UPDATE shop_predmety
JOIN `$temporaryTable` AS import
    ON shop_predmety.kod_predmetu = import.kod_predmetu
    AND shop_predmety.model_rok = import.model_rok
SET
    shop_predmety.nazev = import.nazev,
    shop_predmety.cena_aktualni = import.cena_aktualni,
    shop_predmety.stav = import.stav,
    shop_predmety.nabizet_do = import.nabizet_do,
    shop_predmety.kusu_vyrobeno = import.kusu_vyrobeno,
    shop_predmety.typ = import.typ,
    shop_predmety.ubytovani_den = import.ubytovani_den,
    shop_predmety.popis = import.popis
WHERE TRUE -- už vyřešeno přes INNER JOIN a unique key
SQL,
    );
    $pocetZmenenych = dbAffectedOrNumRows($mysqliResult);

    $mysqliResult = dbQuery(<<<SQL
INSERT INTO shop_predmety (`model_rok`, `nazev`, `kod_predmetu`, `cena_aktualni`, `stav`,  `nabizet_do`, `kusu_vyrobeno`, `typ`, `ubytovani_den`, `popis`)
SELECT import.`model_rok`,
    import.`nazev`,
    import.`kod_predmetu`,
    import.`cena_aktualni`,
    import.`stav`,
    import.`nabizet_do`,
    import.`kusu_vyrobeno`,
    import.`typ`,
    import.`ubytovani_den`,
    import.`popis`
FROM `$temporaryTable` AS import
LEFT JOIN shop_predmety AS uz_zname
    ON uz_zname.kod_predmetu = import.kod_predmetu
        AND uz_zname.model_rok = import.model_rok
WHERE uz_zname.id_predmetu IS NULL -- LEFT JOIN takže NULL je tam, kde jsme záznam přes ON podmínky nenašli
SQL,
    );
    $pocetNovych  = dbAffectedOrNumRows($mysqliResult);

    dbQuery(<<<SQL
UPDATE shop_predmety AS stare
LEFT JOIN `$temporaryTable` AS import
    ON stare.kod_predmetu = import.kod_predmetu
    AND stare.model_rok = import.model_rok
SET stare.stav = $0
WHERE import.id_predmetu IS NULL -- LEFT JOIN takže NULL je tam, kde jsme záznam přes ON podmínky nenašli
SQL,
        [0 => StavPredmetu::MIMO],
    );
    $pocetVyrazenych = dbAffectedOrNumRows($mysqliResult);

    dbQuery(<<<SQL
DROP TEMPORARY TABLE `$temporaryTable`
SQL,
    );
}

oznameni("Import dokončen. Přidáno {$pocetNovych} nových položek, upraveno {$pocetZmenenych} stávajících, vyřazeno {$pocetVyrazenych} starých.");
