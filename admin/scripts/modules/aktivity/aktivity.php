<?php

use Gamecon\XTemplate\XTemplate;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Pravo;
use Gamecon\Aktivita\HromadneAkceAktivit;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as Sql;

/**
 * Stránka pro tvorbu a správu aktivit.
 *
 * nazev: Aktivity
 * pravo: 102
 * submenu_group: 1
 * submenu_order: 3
 * submenu_nazev: Přehled Aktivit
 *
 * @var Uzivatel $u
 * @var SystemoveNastaveni $systemoveNastaveni
 */

if (post('smazat')) {
    $a = Aktivita::zId(
        id: post('aktivitaId'),
        systemoveNastaveni: $systemoveNastaveni,
    );
    if ($a) {
        $a->smaz($u);
    }
    back();
}

if (post('publikovat')) {
    $a = Aktivita::zId(
        id: post('aktivitaId'),
        systemoveNastaveni: $systemoveNastaveni,
    );
    if ($a) {
        Aktivita::publikuj($a);
    }
    back();
}

if (post('pripravit')) {
    $a = Aktivita::zId(
        id: post('aktivitaId'),
        systemoveNastaveni: $systemoveNastaveni,
    );
    if ($a) {
        Aktivita::priprav($a);
    }
    back();
}

if (post('odpripravit')) {
    $a = Aktivita::zId(
        id: post('aktivitaId'),
        systemoveNastaveni: $systemoveNastaveni,
    );
    if ($a) {
        Aktivita::odpriprav($a);
    }
    back();
}

if (post('aktivovat')) {
    $a = Aktivita::zId(
        id: post('aktivitaId'),
        systemoveNastaveni: $systemoveNastaveni,
    );
    if ($a) {
        Aktivita::aktivuj($a);
    }
    back();
}

if (post('deaktivovat') && $u->maRoliSefProgramu()) {
    $a = Aktivita::zId(
        id: post('aktivitaId'),
        systemoveNastaveni: $systemoveNastaveni,
    );
    if ($a) {
        Aktivita::deaktivuj($a);
    }
    back();
}

if (post('odpublikovat')) {
    $a = Aktivita::zId(
        id: post('aktivitaId'),
        systemoveNastaveni: $systemoveNastaveni,
    );
    if ($a) {
        Aktivita::odpublikuj($a);
    }
    back();
}

if (post('aktivovatVse') && $u->maPravo(Pravo::HROMADNA_AKTIVACE_AKTIVIT)) {
    $hromadneAktivovano = (new HromadneAkceAktivit($systemoveNastaveni))->hromadneAktivovatRucne($u);
    oznameni("Hromadně aktivováno $hromadneAktivovano aktivit");
    back();
}

if (post('instance')) {
    Aktivita::zId(
        id: post('aktivitaId'),
        systemoveNastaveni: $systemoveNastaveni,
    )?->instancuj();
    back();
}

if (($opacnyStavKorekce = post("korekce") !== null)) {
    $idAktivity = post('aktivitaId');
    if (!$idAktivity) {
        chyba('Chybí ID aktivity pro korekci.');
        back();
    }
    $a = Aktivita::zId(
        id: $idAktivity,
        systemoveNastaveni: $systemoveNastaveni,
    );
    if (!$a) {
        chyba("Aktivita s ID $idAktivity neexistuje.");
        back();
    }
    $a->nastavKorekci((bool)$opacnyStavKorekce);
}

require_once __DIR__ . '/_filtr-moznosti.php';

$filtrMoznosti = FiltrMoznosti::vytvorZGlobals(FiltrMoznosti::NEFILTROVAT_PODLE_ROKU);

$filtrMoznosti->zobraz();

[$filtr, $razeni] = $filtrMoznosti->dejFiltr();
$aktivity = Aktivita::zFiltru(
    systemoveNastaveni: $systemoveNastaveni,
    filtr: $filtr,
    razeni: $razeni,
);

if (defined('TESTING') && TESTING && !empty($filtr['typ']) && post('smazatVsechnyTypu')) {
    foreach ($aktivity as $aktivita) {
        $aktivita->smaz($u);
    }
    back();
}

$tpl = new XTemplate(__DIR__ . '/aktivity.xtpl');

$currentRequestUrl      = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$exportImportAktivitUrl = $currentRequestUrl . '/export-import-aktivit';
$tpl->assign('urlProExportAktivit', $exportImportAktivitUrl);
$tpl->assign('urlProImportAktivit', $exportImportAktivitUrl);
$exportImportUcastnikuUrl = $currentRequestUrl . '/export-import-ucastniku';
$tpl->assign('urlProExportUcastniku', $exportImportUcastnikuUrl);
$tpl->assign('urlProImportUcastniku', $exportImportUcastnikuUrl);
$tpl->parse('aktivity.exportImport');

if (defined('TESTING') && TESTING && !empty($filtr['typ'])) {
    $tpl->assign('pocet', count($aktivity));
    $idTypu = $filtr['typ'];
    $tpl->assign('id_typu', $idTypu);
    $tpl->assign('nazev_typu', TypAktivity::zId($idTypu)->nazev());
    $tpl->parse('aktivity.smazatTyp');
}

$typy    = dbArrayCol('SELECT id_typu, typ_1p FROM akce_typy');
$typy[0] = '';

$mistnosti = dbArrayCol('SELECT id_lokace, nazev FROM akce_lokace');

foreach ($aktivity as $aktivita) {
    $r = $aktivita->rawDb();
    $tpl->assign([
        'id_akce'      => $aktivita->id(),
        'nazev_akce'   => $aktivita->nazev(),
        'hinted'       => $aktivita->tagy()
            ? 'hinted'
            : '',
        'cas'          => $aktivita->denCasSkutecny(),
        'organizatori' => $aktivita->orgJmena(),
        'typ'          => $typy[$r['typ']],
        'mistnost'     => $mistnosti[$r['lokace']] ?? '(žádná)',
    ]);
    if ($aktivita->tagy()) {
        $tpl->assign('tagy', implode(' | ', $aktivita->tagy()));
        $tpl->parse('aktivity.aktivita.hint');
    }

    if ($r[Sql::PATRI_POD]) {
        $tpl->parse('aktivity.aktivita.symbolInstance');
    }
    if ($u->maPravo(Pravo::PROVADI_KOREKCE)) {
        $tpl->assign('opacnyStavKorekce', $aktivita->probehlaKorekce() ? 1 : 0);
        $tpl->parse("aktivity.aktivita.rychlokorekce1");
    }
    if ($r[Sql::PROBEHLA_KOREKCE]) {
        $tpl->parse('aktivity.aktivita.symbolKorekce');
    } else {
        $tpl->parse('aktivity.aktivita.symbolBezKorekce');
    }
    if ($u->maPravo(Pravo::PROVADI_KOREKCE)) {
        $tpl->parse("aktivity.aktivita.rychlokorekce2");
    }
    if ($r[Sql::STAV] == StavAktivity::NOVA) {
        $tpl->parse('aktivity.aktivita.tlacitka.publikovat');
    } elseif ($r[Sql::STAV] == StavAktivity::PUBLIKOVANA) {
        $tpl->parse('aktivity.aktivita.tlacitka.pripravit');
        $tpl->parse('aktivity.aktivita.tlacitka.odpublikovat');
    } elseif ($r[Sql::STAV] == StavAktivity::PRIPRAVENA) {
        $tpl->parse('aktivity.aktivita.tlacitka.odpripravit');
        $tpl->parse('aktivity.aktivita.tlacitka.aktivovat');
    } elseif ($r[Sql::STAV] == StavAktivity::AKTIVOVANA && $u->maRoliSefProgramu()) {
        if ($aktivita->pocetPrihlasenych() > 0) {
            $tpl->assign('pocetPrihlasenych', $aktivita->pocetPrihlasenych());
            if ($aktivita->pocetPrihlasenych() === 1) {
                $tpl->parse('aktivity.aktivita.tlacitka.deaktivovat.potvrditDeaktivaciSPrihlasenymi.jedenPrihlaseny');
            } elseif ($aktivita->pocetPrihlasenych() <= 4) {
                $tpl->parse('aktivity.aktivita.tlacitka.deaktivovat.potvrditDeaktivaciSPrihlasenymi.nekolikPrihlasenych');
            } else {
                $tpl->parse('aktivity.aktivita.tlacitka.deaktivovat.potvrditDeaktivaciSPrihlasenymi.hodnePrihlasenych');
            }
            $tpl->parse('aktivity.aktivita.tlacitka.deaktivovat.potvrditDeaktivaciSPrihlasenymi');
        }
        $tpl->parse('aktivity.aktivita.tlacitka.deaktivovat');
    }
    $tpl->parse('aktivity.aktivita.tlacitka');
    $tpl->parse('aktivity.aktivita');
}

$tpl->assign(
    'aktivovatVseDisabled',
    $filtr != ['rok' => ROCNIK] || !$u->maPravo(Pravo::HROMADNA_AKTIVACE_AKTIVIT)
        ? 'disabled'
        : '',
);
$tpl->parse('aktivity.aktivovatVse');

$tpl->parse('aktivity');
$tpl->out('aktivity');
