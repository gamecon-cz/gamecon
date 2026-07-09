<?php

use Gamecon\Pravo;
use Gamecon\Shop\ShopUbytovani;
use Gamecon\XTemplate\XTemplate;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura as UzivatelSql;

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

// Report ubytování je vždy XLSX; použijeme přímo XLSX reader místo hádání podle MIME
// (mime_content_type je nespolehlivý – u přeuloženého Excelu vrací i application/zip apod.
// a ReaderFactory pak hodí neošetřenou výjimku → "stránka nefunguje").
$reader = new XlsxReader();
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
$indexUbytovanS       = $hlavicka['ubytovan_s'] ?? null;
$indexCisloDokladu    = $hlavicka['cislo_dokladu'] ?? null;
$indexStatniObcanstvi = $hlavicka['statni_obcanstvi'] ?? null;

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
                // "typ" z reportu je kód předmětu bez poslední 3znakové přípony dne (viz finance-report-ubytovani.php);
                // dohledáme podle něj + dnů, nezávisle na názvu předmětu.
                $idsUbytovani = ShopUbytovani::dejIdsPredmetuUbytovaniPodleKoduTypu($jedinyTyp, $dny);
            }
            $zapsanoZmenVTransakci += ShopUbytovani::ulozObjednaneUbytovaniUcastnika(
                $idsUbytovani,
                $ucastnik,
                false,
                povolitJednuNoc: $ucastnik->maPravo(Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC),
            );
            if ($indexUbytovanS !== null) {
                $zapsanoZmenVTransakci += ShopUbytovani::ulozSKymChceBytNaPokoji(
                    trim((string)$radek[$indexUbytovanS]),
                    $ucastnik,
                );
            }
            if ($indexCisloDokladu !== null) {
                $cisloDokladu   = trim((string)$radek[$indexCisloDokladu]);
                $zasifrovaneOp  = $ucastnik->rawDb()[UzivatelSql::OP] ?? '';
                $soucasneCislo  = $zasifrovaneOp !== ''
                    ? Sifrovatko::desifruj($zasifrovaneOp)
                    : '';
                if ($cisloDokladu === '') {
                    // prázdná buňka = případné smazání existujícího dokladu (jen s explicitním povolením)
                    if ($soucasneCislo !== '') {
                        if ($povolitMazaniOp) {
                            $ucastnik->cisloOp('');
                            $ucastnik->typDokladuTotoznosti('');
                            $zapsanoZmenVTransakci++;
                        } else {
                            $a          = $u->koncovkaDlePohlavi();
                            $varovani[] = "Účastník {$ucastnik->jmenoNick()} z řádku {$poradiRadku} má prázdné 'cislo_dokladu' ale mazání OP jsi nepovolil{$a}";
                        }
                    }
                } elseif ($cisloDokladu !== $soucasneCislo) {
                    // neprázdná buňka = přepsat číslo dokladu (i když už nějaké má)
                    $ucastnik->cisloOp($cisloDokladu);
                    $zapsanoZmenVTransakci++;
                }
            }
            if ($indexStatniObcanstvi !== null) {
                // Občanství přepíšeme jen když je buňka vyplněná (prázdnou nechceme mazat existující údaj).
                $statniObcanstvi = trim((string)$radek[$indexStatniObcanstvi]);
                if ($statniObcanstvi !== '' && $statniObcanstvi !== $ucastnik->statniObcanstvi()) {
                    $ucastnik->statniObcanstvi($statniObcanstvi);
                    $zapsanoZmenVTransakci++;
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
