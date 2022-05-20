<?php
if (post('importBalicku')) {
    $f = fopen($_FILES['souborSBalicky']['tmp_name'], 'rb');
    if (!$f) {
        throw new Chyba('Soubor se nepodařilo načíst');
    }

    $separator = ';';
    if (post('formatSouboruSBalicky') === ',') {
        $separator = ',';
    }

    $hlavickaKlice = array_map(static function (string $klic) {
        // protože tam některé programy při ukládání cpou na začátek zero-width non-breaking space https://en.wiktionary.org/wiki/ZWNBSP
        return preg_replace('~[^[:alnum:]_]~', '', $klic);
    }, fgetcsv($f, null, $separator));
    $hlavicka = array_flip($hlavickaKlice);
    if (!array_keys_exist(['id_uzivatele', 'balicek'], $hlavicka)) {
        throw new Chyba('Chybný formát souboru - musí mít sloupce id_uzivatele a balicek');
    }

    $indexIdUzivatele = $hlavicka['id_uzivatele'];
    $indexBalicek = $hlavicka['balicek'];

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
    while ($radek = fgetcsv($f, null, $separator)) {
        $idUzivatele = (int)$radek[$indexIdUzivatele];
        if (!$idUzivatele) {
            continue;
        }
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

    oznameni("Import dokončen. " . ($zapsanoZmen > 0 ? "Změněno $zapsanoZmen záznamů." : 'Beze změny.'));
}

$importTemplate = new XTemplate(__DIR__ . '/_uvod-import-balicku.xtpl');
$importTemplate->assign('baseUrl', URL_ADMIN);

$importTemplate->parse('import');
$importTemplate->out('import');
