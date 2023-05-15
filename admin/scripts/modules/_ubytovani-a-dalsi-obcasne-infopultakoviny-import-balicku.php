<?php

use Gamecon\XTemplate\XTemplate;
use OpenSpout\Reader\Common\Creator\ReaderFactory;

$souborInputName = 'souborSBalicky';

if (!post('importBalicku')) {
    $importTemplate = new XTemplate(__DIR__ . '/_ubytovani-a-dalsi-obcasne-infopultakoviny-import-balicku.xtpl');
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

$dejPoznamkuOVelkemBalicku = static fn(string $balicek, int $rok): string => str_contains($balicek, 'v')
    ? "velký balíček $rok"
    : '';

$reader = ReaderFactory::createFromFileByMimeType($vstupniSoubor);
$reader->open($vstupniSoubor);

$reader->open($vstupniSoubor);

$reader->getSheetIterator()->rewind();
/** @var \OpenSpout\Reader\SheetInterface $sheet */
$sheet = $reader->getSheetIterator()->current();

$rowIterator = $sheet->getRowIterator();
$rowIterator->rewind();
/** @var \OpenSpout\Common\Entity\Row|null $hlavicka */
$row      = $rowIterator->current();
$hlavicka = array_flip($row->toArray());
if (!array_keys_exist(['id_uzivatele', 'balicek'], $hlavicka)) {
    throw new Chyba('Chybný formát souboru - musí mít sloupce id_uzivatele a balicek');
}

$indexIdUzivatele = $hlavicka['id_uzivatele'];
$indexBalicek     = $hlavicka['balicek'];

$rowIterator->next();

$chyby         = [];
$varovani      = [];
$balickyProSql = [];
$poradiRadku   = 1;
/** @var \OpenSpout\Common\Entity\Row|null $row */
while ($rowIterator->valid()) {
    $radek = $rowIterator->current()->toArray();
    $poradiRadku++;
    $rowIterator->next();

    if ($radek) {
        $idUzivatele = (int)($radek[$indexIdUzivatele] ?? null);
        if (!$idUzivatele) {
            $chyby[] = sprintf(
                'Na řádku %d chybí ID účastníka očekávaný v %d. sloupci',
                $poradiRadku,
                $indexIdUzivatele + 1,
            );
            continue;
        }

        $uzivatel = Uzivatel::zId($idUzivatele);
        if (!$uzivatel) {
            $chyby[] = sprintf(
                'Účastník s ID %d z řádku %d nexistuje',
                $idUzivatele,
                $poradiRadku,
            );
            continue;
        }
        if (!$uzivatel->gcPrihlasen()) {
            $varovani[] = sprintf(
                'Účastník %s z řádku %d není přihlášen na letošní Gamecon a byl přeskočen',
                $uzivatel->jmenoNick(),
                $poradiRadku,
            );
            continue;
        }

        $balicek       = trim((string)($radek[$indexBalicek] ?? ''));
        $balicekProSql = $dejPoznamkuOVelkemBalicku($balicek, ROCNIK);
        if ($balicekProSql === ''
            && !in_array(
                strtolower(removeDiacritics($balicek)),
                ['', 'balicek'/** exportovaný název bez diakritiky, viz report-infopult-ucastnici-balicky.php */])
        ) {
            $chyby[] = sprintf(
                "U účastníka %s z řádku %d je neznámý zápis balíčku '%s' - očekáváme nic, 'balíček' nebo 'velký balíček'",
                $uzivatel->jmenoNick(),
                $poradiRadku,
                $balicek,
            );
            continue;
        }
        if ($balicekProSql && !$uzivatel->shop()->koupilNejakouVec()) {
            $varovani[] = sprintf(
                "Účastník %s z řádku %d si nic neobjednal a nemůže proto mít velký balíček",
                $uzivatel->jmenoNick(),
                $poradiRadku,
            );
            continue;
        }
        $balickyProSql[$idUzivatele] = $balicekProSql;
    }
}
$reader->close();

if ($chyby) {
    throw new Chyba('Chybička se vloudila: ' . implode("; ", $chyby));
}

if ($varovani) {
    varovani('Drobnosti: ' . implode(',', $varovani), false);
}

$zapsanoZmen = 0;
if ($balickyProSql) {
    $temporaryTable = uniqid('import_balicku_tmp_', true);
    dbQuery(<<<SQL
CREATE TEMPORARY TABLE `$temporaryTable`
(id_uzivatele INT UNSIGNED NOT NULL PRIMARY KEY, infopult_poznamka VARCHAR(128) DEFAULT NULL)
SQL,
    );

    $queryParams    = [];
    $sqlValuesArray = [];
    $paramIndex     = 0;
    foreach ($balickyProSql as $idUzivatele => $balicekProSql) {
        $queryParams[]    = $idUzivatele;
        $queryParams[]    = $balicekProSql;
        $sqlValuesArray[] = '($' . $paramIndex++ . ',$' . $paramIndex++ . ')';
    }

    $sqlValues = implode(",\n", $sqlValuesArray);

    dbQuery(<<<SQL
INSERT INTO `$temporaryTable` (id_uzivatele, infopult_poznamka)
    VALUES
$sqlValues
SQL,
        $queryParams,
    );

    $mysqliResult = dbQuery(<<<SQL
UPDATE uzivatele_hodnoty
JOIN `$temporaryTable` ON uzivatele_hodnoty.id_uzivatele = `$temporaryTable`.id_uzivatele
SET uzivatele_hodnoty.infopult_poznamka = `$temporaryTable`.infopult_poznamka
WHERE TRUE
SQL,
    );
    $zapsanoZmen += dbAffectedOrNumRows($mysqliResult);

    dbQuery(<<<SQL
DROP TEMPORARY TABLE `$temporaryTable`
SQL,
    );
}

oznameni("Import dokončen. " . ($zapsanoZmen > 0 ? "Změněno $zapsanoZmen záznamů." : 'Beze změny.'));
