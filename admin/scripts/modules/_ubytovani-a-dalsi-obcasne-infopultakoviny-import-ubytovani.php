<?php

if (!post('pokojeImport')) {
    $importTemplate = new XTemplate(__DIR__ . '/_ubytovani-a-dalsi-obcasne-infopultakoviny-import-ubytovani.xtpl');
    $importTemplate->assign('baseUrl', URL_ADMIN);
    $importTemplate->assign('ubytovaniReport', basename(__DIR__ . '/../zvlastni/reporty/finance-report-ubytovani.php', '.php'));

    $importTemplate->parse('import');
    $importTemplate->out('import');

    return;
}

if (!is_readable($_FILES['pokojeSoubor']['tmp_name'])) {
    throw new Chyba('Soubor se nepodařilo načíst');
}

$zapsanoZmen = 0;

$reader = \OpenSpout\Reader\Common\Creator\ReaderEntityFactory::createXLSXReader();

$reader->open($_FILES['pokojeSoubor']['tmp_name']);

$reader->getSheetIterator()->rewind();
/** @var \OpenSpout\Reader\SheetInterface $sheet */
$sheet = $reader->getSheetIterator()->current();

$rowIterator = $sheet->getRowIterator();
$rowIterator->rewind();
/** @var \OpenSpout\Common\Entity\Row|null $hlavicka */
$row = $rowIterator->current();
$hlavicka = array_flip($row->toArray());
$vyzadovaneSloupce = ['id_uzivatele', 'prvni_noc', 'posledni_noc', 'pokoj'];
if (!array_keys_exist($vyzadovaneSloupce, $hlavicka)) {
    throw new Chyba('Chybný formát souboru - musí mít sloupce ' . implode(', ', $vyzadovaneSloupce));
}
$indexIdUzivatele = $hlavicka['id_uzivatele'];
$indexPrvniNoc = $hlavicka['prvni_noc'];
$indexPosledniNoc = $hlavicka['posledni_noc'];
$indexPokoj = $hlavicka['pokoj'];

$rowIterator->next();

$chyby = [];
$varovani = [];
$balickyProSql = [];
$poradiRadku = 1;
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

        $pokoj = trim((string)$radek[$indexPokoj]);
        $prvniNoc = trim((string)$radek[$indexPrvniNoc]) === ''
            ? (int)$radek[$indexPrvniNoc]
            : null;
        $posledniNoc = trim((string)$radek[$indexPosledniNoc]) === ''
            ? (int)$radek[$indexPosledniNoc]
            : null;
        try {
            $zapsanoZmen += ShopUbytovani::ulozUbytovaniUzivatele($pokoj, $prvniNoc, $posledniNoc, $uzivatel);
        } catch (Chyba $chyba) {
            $chyby[] = $chyba->getMessage();
            continue;
        }
    }
}
$reader->close();

if ($chyby) {
    throw new Chyba('Chybička se vloudila: ' . implode("; ", $chyby));
}

if ($varovani) {
    varovani('Drobnosti: ' . implode(',', $varovani), false);
}

oznameni("Import dokončen. " . ($zapsanoZmen > 0 ? "Změněno $zapsanoZmen záznamů (jeden záznam na každou noc)." : 'Beze změny.'));
