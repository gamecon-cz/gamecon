<?php

use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;

if (!post('importBalicku')) {

    $importTemplate = new XTemplate(__DIR__ . '/_uvod-import-balicku.xtpl');
    $importTemplate->assign('baseUrl', URL_ADMIN);

    $importTemplate->parse('import');
    $importTemplate->out('import');

    return;
}

if (!is_readable($_FILES['souborSBalicky']['tmp_name'])) {
    throw new Chyba('Soubor se nepodařilo načíst');
}

$dejPoznamkuOVelkemBalicku = static function (string $balicek, int $rok): string {
    $balicek = trim($balicek);
    if ($balicek === '') {
        return '';
    }
    $bezBilychZnaku = preg_replace('~\s~', '', $balicek);
    $bezDiakritiky = removeDiacritics($bezBilychZnaku);

    return str_starts_with($bezDiakritiky, 'velky')
        ? "velký balíček $rok"
        : '';
};

$maNejakyNakupSql = static function (int $rok) {
    return <<<SQL
EXISTS(SELECT 1 FROM shop_nakupy WHERE shop_nakupy.id_uzivatele = uzivatele_hodnoty.id_uzivatele AND shop_nakupy.rok = $rok)
SQL;
};

$zapsanoZmen = 0;

$reader = ReaderEntityFactory::createXLSXReader();

$reader->open($_FILES['souborSBalicky']['tmp_name']);

$reader->getSheetIterator()->rewind();
/** @var \Box\Spout\Reader\SheetInterface $sheet */
$sheet = $reader->getSheetIterator()->current();

$rowIterator = $sheet->getRowIterator();
$rowIterator->rewind();
/** @var \Box\Spout\Common\Entity\Row|null $hlavicka */
$row = $rowIterator->current();
$hlavicka = array_flip($row->toArray());
if (!array_keys_exist(['id_uzivatele', 'balicek'], $hlavicka)) {
    throw new Chyba('Chybný formát souboru - musí mít sloupce id_uzivatele a balicek');
}

$indexIdUzivatele = $hlavicka['id_uzivatele'];
$indexBalicek = $hlavicka['balicek'];

$rowIterator->next();

$radky = [];
/** @var \Box\Spout\Common\Entity\Row|null $row */
while ($rowIterator->valid()) {
    $radek = $rowIterator->current()->toArray();

    if ($radek) {
        $idUzivatele = (int)($radek[$indexIdUzivatele] ?? null);
        $radky[$idUzivatele] = $radek;
    }

    $rowIterator->next();
}

if ($radky) {

    $temporaryTable = uniqid('import_balicku_tmp_', true);
    dbQuery(<<<SQL
CREATE TEMPORARY TABLE `$temporaryTable`
(id_uzivatele INT UNSIGNED NOT NULL PRIMARY KEY, infopult_poznamka VARCHAR(128) DEFAULT NULL)
SQL
    );

    $queryParams = [];
    $sqlValuesArray = [];
    $paramIndex = 0;
    foreach ($radky as $idUzivatele => $radek) {
        $queryParams[] = $idUzivatele;
        $queryParams[] = $dejPoznamkuOVelkemBalicku((string)$radek[$indexBalicek], ROK);
        $sqlValuesArray[] = '($' . $paramIndex++ . ',$' . $paramIndex++ . ')';
    }

    $sqlValues = implode(",\n", $sqlValuesArray);

    dbQuery(<<<SQL
INSERT INTO `$temporaryTable` (id_uzivatele, infopult_poznamka)
    VALUES
$sqlValues
SQL,
        $queryParams
    );

    $mysqliResult = dbQuery(<<<SQL
UPDATE uzivatele_hodnoty
JOIN `$temporaryTable` ON uzivatele_hodnoty.id_uzivatele = `$temporaryTable`.id_uzivatele
-- pouze pokud má účastník letos nějaký nákup, tak může mít velký balíček
SET uzivatele_hodnoty.infopult_poznamka = IF ({$maNejakyNakupSql(ROK)}, `$temporaryTable`.infopult_poznamka, '')
SQL
    );
    $zapsanoZmen += dbNumRows($mysqliResult);

    dbQuery(<<<SQL
DROP TEMPORARY TABLE `$temporaryTable`
SQL
    );
}

$reader->close();

oznameni("Import dokončen. " . ($zapsanoZmen > 0 ? "Změněno $zapsanoZmen záznamů." : 'Beze změny.'));
