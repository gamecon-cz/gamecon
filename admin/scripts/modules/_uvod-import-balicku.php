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

/** @var \Box\Spout\Reader\SheetInterface $sheet */
foreach ($reader->getSheetIterator() as $sheet) {
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
    /** @var \Box\Spout\Common\Entity\Row|null $row */
    while ($rowIterator->valid()) {
        $radek = $rowIterator->current()->toArray();

        if ($radek) {
            $idUzivatele = (int)($radek[$indexIdUzivatele] ?? null);
            if ($idUzivatele) {
                $mysqliResult = dbQuery(<<<SQL
UPDATE uzivatele_hodnoty
-- pouze pokud má účastník letos nějaký nákup, tak může mít velký balíček
SET infopult_poznamka = IF ({$maNejakyNakupSql(ROK)}, $0, '')
WHERE id_uzivatele = $1
SQL,
                    [$dejPoznamkuOVelkemBalicku((string)$radek[$indexBalicek], ROK), $radek[$indexIdUzivatele]]
                );
                $zapsanoZmen += dbNumRows($mysqliResult);
            }
        }

        $rowIterator->next();
    }
}

$reader->close();

oznameni("Import dokončen. " . ($zapsanoZmen > 0 ? "Změněno $zapsanoZmen záznamů." : 'Beze změny.'));
