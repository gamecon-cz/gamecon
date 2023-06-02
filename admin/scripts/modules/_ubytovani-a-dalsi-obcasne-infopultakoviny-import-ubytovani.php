<?php

use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Shop\ShopUbytovani;
use Gamecon\XTemplate\XTemplate;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use Gamecon\Uzivatel\SqlStruktura\UzivatelSqlStruktura as UzivatelSql;

/**
 * @var Uzivatel $u
 */

$souborInputName = 'pokojeSoubor';

if (!post('pokojeImport')) {
    $importTemplate = new XTemplate(__DIR__ . '/_ubytovani-a-dalsi-obcasne-infopultakoviny-import-ubytovani.xtpl');
    $importTemplate->assign('souborInputName', $souborInputName);
    $importTemplate->assign('baseUrl', URL_ADMIN);
    $importTemplate->assign('ubytovaniReport', basename(__DIR__ . '/../zvlastni/reporty/finance-report-ubytovani.php', '.php'));

    $importTemplate->parse('import');
    $importTemplate->out('import');

    return;
}

$vstupniSoubor   = $_FILES[$souborInputName]['tmp_name'] ?? '';
$povolitMazaniOp = post('povolitMazaniOp');

if (!is_readable($vstupniSoubor)) {
    throw new Chyba('Soubor se nepodařilo načíst');
}

$zapsanoZmenPerUcastnik = 0;

$reader = ReaderFactory::createFromFileByMimeType($vstupniSoubor);
$reader->open($vstupniSoubor);

$reader->getSheetIterator()->rewind();
/** @var \OpenSpout\Reader\SheetInterface $sheet */
$sheet = $reader->getSheetIterator()->current();

$rowIterator = $sheet->getRowIterator();
$rowIterator->rewind();
/** @var \OpenSpout\Common\Entity\Row|null $hlavicka */
$row               = $rowIterator->current();
$hlavicka          = array_flip($row->toArray());
$vyzadovaneSloupce = ['id_uzivatele', 'prvni_noc', 'posledni_noc', 'pokoj', 'typ'];
if (!array_keys_exist($vyzadovaneSloupce, $hlavicka)) {
    throw new Chyba('Chybný formát souboru - musí mít sloupce ' . implode(', ', $vyzadovaneSloupce));
}
$indexIdUzivatele  = $hlavicka['id_uzivatele'];
$indexPrvniNoc     = $hlavicka['prvni_noc'];
$indexPosledniNoc  = $hlavicka['posledni_noc'];
$indexPokoj        = $hlavicka['pokoj'];
$indexTyp          = $hlavicka['typ'];
$indexUbytovanS    = $hlavicka['ubytovan_s'] ?? null;
$indexCisloDokladu = $hlavicka['cislo_dokladu'] ?? null;

$zasifrovanePrazdneOp = Sifrovatko::zasifruj('');

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

        $ucastnik = Uzivatel::zId($idUzivatele);
        if (!$ucastnik) {
            $chyby[] = sprintf(
                'Účastník s ID %d z řádku %d nexistuje',
                $idUzivatele,
                $poradiRadku,
            );
            continue;
        }
        if (!$ucastnik->gcPrihlasen()) {
            $varovani[] = sprintf(
                'Účastník %s z řádku %d není přihlášen na letošní Gamecon a byl přeskočen',
                $ucastnik->jmenoNick(),
                $poradiRadku,
            );
            continue;
        }
        $typyString        = trim((string)$radek[$indexTyp]);
        $typy              = array_map('trim', explode(',', $typyString));
        $pokoj             = trim((string)$radek[$indexPokoj]); // prázdný pokoj = smazat záznam o přiřazeném pokoji
        $prvniNocString    = trim((string)$radek[$indexPrvniNoc]);
        $prvniNoc          = $prvniNocString !== ''
            ? (int)$prvniNocString
            : null;
        $posledniNocString = trim((string)$radek[$indexPosledniNoc]);
        $posledniNoc       = $posledniNocString !== ''
            ? (int)$posledniNocString
            : null;

        if (($prvniNoc === null && $posledniNoc !== null) || ($prvniNoc !== null && $posledniNoc === null)) {
            $chyby[] = sprintf(
                "První a poslední noc musí být buďto obě prázdné, nebo obě zadané. Účastník %s z řádku %d má první noc $prvniNoc a poslední noc $posledniNoc",
                $ucastnik->jmenoNick(),
                $poradiRadku,
            );
            continue;
        }

        if (count($typy) === 0 && ($prvniNoc ?? $posledniNoc) !== null) {
            $chyby[] = sprintf(
                "Nelze iportovat dny bez typu ubytování. Účastník %s z řádku %d má typ $typyString, první noc $prvniNoc a poslední noc $posledniNoc",
                $ucastnik->jmenoNick(),
                $poradiRadku,
            );
            continue;
        }

        if (count($typy) > 1 && ($prvniNoc ?? $posledniNoc) !== null) {
            $chyby[] = sprintf(
                "Nelze iportovat více než jeden typ ubytování. Účastník %s z řádku %d má typy $typyString",
                $ucastnik->jmenoNick(),
                $poradiRadku,
            );
            continue;
        }

        $zapsanoZmenVTransakci = 0;
        try {
            dbBegin();
            $zapsanoZmenVTransakci += ShopUbytovani::ulozPokojUzivatele($pokoj, $prvniNoc, $posledniNoc, $ucastnik);
            $idsUbytovani          = []; // když je sezam pokojů prázdný, tak to smaže všechny letošní objednávky pokojů účastníka
            if (($prvniNoc ?? $posledniNoc) !== null && count($typy) === 1) {
                $dny          = range($prvniNoc, $posledniNoc);
                $jedinyTyp    = reset($typy);
                $typyPoDnech  = array_map(static function (int $den) use ($jedinyTyp) {
                    return $jedinyTyp . ' ' . DateTimeGamecon::denPodleIndexuOdZacatkuGameconu($den);
                }, $dny);
                $idsUbytovani = ShopUbytovani::dejIdsPredmetuUbytovani($typyPoDnech);
            }
            $zapsanoZmenVTransakci += ShopUbytovani::ulozObjednaneUbytovaniUcastnika(
                $idsUbytovani,
                $ucastnik,
                false,
            );
            if ($indexUbytovanS !== null) {
                $zapsanoZmenVTransakci += ShopUbytovani::ulozSKymChceBytNaPokoji(
                    trim((string)$radek[$indexUbytovanS]),
                    $ucastnik,
                );
            }
            if ($indexCisloDokladu !== null) {
                $cisloDokladu = trim((string)$radek[$indexCisloDokladu]);
                if ($cisloDokladu === ''
                    && ($ucastnik->rawDb()[UzivatelSql::OP] ?? '') !== ''
                    && ($ucastnik->rawDb()[UzivatelSql::OP] ?? '') !== $zasifrovanePrazdneOp
                ) {
                    if ($povolitMazaniOp) {
                        $ucastnik->cisloOp('');
                        $ucastnik->typDokladu('');
                        $zapsanoZmenVTransakci++;
                    } else {
                        $a          = $u->koncovkaDlePohlavi();
                        $varovani[] = "Účastník {$ucastnik->jmenoNick()} z řádku {$poradiRadku} má prázdné 'cislo_dokladu' ale mazání OP jsi nepovolil{$a}";
                    }
                }
            }
            dbCommit();
            if ($zapsanoZmenVTransakci > 0) {
                $zapsanoZmenPerUcastnik++;
            }
        } catch (Chyba $chyba) {
            dbRollback();
            $chyby[] = sprintf(
                "Účastník %s z řádku %d: %s",
                $ucastnik->jmenoNick(),
                $poradiRadku,
                $chyba->getMessage(),
            );
            continue;
        } catch (\Throwable $throwable) {
            dbRollback();
            throw $throwable;
        }
    }
}
$reader->close();

if ($chyby) {
    chyba('Potíže: ' . implode("; ", $chyby));
}

if ($varovani) {
    varovani('Drobnosti: ' . implode(',', $varovani), false);
}

oznameni("Import dokončen. " . ($zapsanoZmenPerUcastnik > 0 ? "Změněno $zapsanoZmenPerUcastnik záznamů." : 'Beze změny.'));
