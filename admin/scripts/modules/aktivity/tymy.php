<?php

declare(strict_types=1);

/**
 * nazev: Týmy
 * pravo: 102
 * submenu_group: 3
 * submenu_order: 2
 *
 * @var Uzivatel $u
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

use Gamecon\Aktivita\AktivitaTym;
use Gamecon\Cas\DateTimeCz;
use Gamecon\XTemplate\XTemplate;

require_once __DIR__ . '/_tymy-akce.php';
require_once __DIR__ . '/_kontrola-tymu.php';

zpracujAkciTymu($u, $systemoveNastaveni);

$tpl = new XTemplate(__DIR__ . '/tymy.xtpl');

$kontrolaAktivni = (bool)get('kontrolaStavuTymu');

$tpl->assign([
    'kontrola_btn_trida' => $kontrolaAktivni ? 'tlacitko tymy-kontrola-btn tymy-kontrola-btn--aktivni' : 'tlacitko tymy-kontrola-btn',
    'kontrola_btn_href'  => $kontrolaAktivni ? 'aktivity/tymy' : 'aktivity/tymy?kontrolaStavuTymu=1',
]);

if ($kontrolaAktivni) {
    renderujVysledkyKontrolyTymu($tpl);
}

// Všechny teamové aktivity ročníku
$aktivityRows = dbFetchAll(<<<SQL
    SELECT id_akce, nazev_akce, team_min, team_max, team_kapacita, id_turnaje, turnaj_kolo, zacatek
    FROM akce_seznam
    WHERE rok = $0
      AND teamova = 1
    ORDER BY zacatek, nazev_akce
    SQL,
    [0 => ROCNIK],
);

// Načti názvy turnajů
$idsTurnaju = [];
foreach ($aktivityRows as $r) {
    if ($r['id_turnaje'] !== null) {
        $idsTurnaju[(int)$r['id_turnaje']] = true;
    }
}
$nazvyTurnaju = [];
if ($idsTurnaju !== []) {
    foreach (dbFetchAll('SELECT id_turnaje, nazev FROM turnaje WHERE id_turnaje IN ($1)', [1 => array_keys($idsTurnaju)]) as $r) {
        $nazvyTurnaju[(int)$r['id_turnaje']] = $r['nazev'];
    }
}

// Skupiny: turnaje (key=t<id>) + samostatné týmové aktivity (key=a<id>)
$skupiny = [];
foreach ($aktivityRows as $a) {
    $idAkce = (int)$a['id_akce'];
    if ($a['id_turnaje'] !== null) {
        $idTurnaje = (int)$a['id_turnaje'];
        $key       = 't' . $idTurnaje;
        if (!isset($skupiny[$key])) {
            $skupiny[$key] = [
                'nadpis'   => $nazvyTurnaju[$idTurnaje] ?? ('Turnaj #' . $idTurnaje),
                'aktivity' => [],
                'jeTurnaj' => true,
            ];
        }
        $skupiny[$key]['aktivity'][$idAkce] = $a;
    } else {
        $skupiny['a' . $idAkce] = [
            'nadpis'   => $a['nazev_akce'],
            'aktivity' => [$idAkce => $a],
            'jeTurnaj' => false,
        ];
    }
}

foreach ($skupiny as $skupina) {
    // Sesbírej týmy unikátně přes všechny aktivity skupiny
    /** @var array<int, AktivitaTym> $tymy */
    $tymy = [];
    foreach ($skupina['aktivity'] as $idAkce => $_a) {
        foreach (AktivitaTym::vsechnyTymyAktivity($idAkce) as $tym) {
            $tymy[$tym->getId()] = $tym;
        }
    }

    $pocetTymu         = count($tymy);
    $pocetClenuvCelkem = array_sum(array_map(fn($tym) => $tym->pocetClenu(), $tymy));

    // min/max členů — vezmi z první aktivity skupiny (v rámci turnaje obvykle shodné)
    $prvni  = reset($skupina['aktivity']);
    $minMax = '';
    if ($prvni['team_min'] !== null || $prvni['team_max'] !== null) {
        $minMax = ' &nbsp;|&nbsp; členů na tým: ' . ($prvni['team_min'] ?? '?') . '–' . ($prvni['team_max'] ?? '∞');
    }

    $tpl->assign([
        'nadpisSkupiny'     => $skupina['nadpis'],
        'pocetTymu'         => $pocetTymu,
        'pocetClenuvCelkem' => $pocetClenuvCelkem,
        'minMaxClenu'       => $minMax,
    ]);

    if ($tymy === []) {
        $tpl->parse('tymy.skupina.zadneTymy');
    } else {
        foreach ($tymy as $aktivitaTym) {
            $idKapitana = $aktivitaTym->idKapitana();
            $kapitan    = Uzivatel::zId($idKapitana);
            $clenove    = $aktivitaTym->clenoveTymu();
            $obsazenost = count($clenove) . '/' . ($aktivitaTym->limitTymu() ?? '∞');
            $zalozen    = $aktivitaTym->casZalozeniMs()
                ? (new DateTimeCz((new \DateTime('@' . floor($aktivitaTym->casZalozeniMs() / 1000)))->format('Y-m-d H:i:s')))->format('j.n. H:i')
                : '–';

            $tpl->assign([
                'id'         => $aktivitaTym->getId(),
                'nazev'      => $aktivitaTym->getNazev() ?: '(bez názvu)',
                'kapitan'    => $kapitan->login() . ' (' . $kapitan->krestniJmeno() . ' ' . $kapitan->prijmeni() . ')',
                'obsazenost' => $obsazenost,
                'verejny'    => $aktivitaTym->jeVerejny() ? 'veřejný' : 'soukromý',
                'zalozen'    => $zalozen,
            ]);

            // Seznam aktivit týmu v rámci této skupiny (jen u turnajů — u samostatných aktivit je redundantní)
            if ($skupina['jeTurnaj']) {
                $idAktivitTymu = array_intersect($aktivitaTym->idDalsichAktivit(), array_keys($skupina['aktivity']));
                // seřaď podle pořadí v aktivitách skupiny (zacatek)
                $serazeno = array_values(array_filter(
                    array_keys($skupina['aktivity']),
                    fn($id) => in_array($id, $idAktivitTymu, true),
                ));
                foreach ($serazeno as $idAkceTymu) {
                    $a       = $skupina['aktivity'][$idAkceTymu];
                    $zacatek = $a['zacatek']
                        ? (new DateTimeCz($a['zacatek']))->format('j.n. H:i')
                        : '';
                    $kolo    = $a['turnaj_kolo'] !== null ? ' (kolo ' . (int)$a['turnaj_kolo'] . ')' : '';
                    $tpl->assign([
                        'at_nazev'   => $a['nazev_akce'],
                        'at_kolo'    => $kolo,
                        'at_zacatek' => $zacatek,
                    ]);
                    $tpl->parse('tymy.skupina.tym.aktivityTymu.aktivitaTymu');
                }
                $tpl->parse('tymy.skupina.tym.aktivityTymu');
            }

            $maily = [];
            foreach ($clenove as $clen) {
                $jeKapitan = $clen->id() === $idKapitana;
                $tpl->assign([
                    'nick'       => $clen->login(),
                    'jmeno'      => $clen->krestniJmeno() . ' ' . $clen->prijmeni(),
                    'mail'       => $clen->mail(),
                    'je_kapitan' => $jeKapitan ? ' (kapitán)' : '',
                    'trida'      => $jeKapitan ? ' class="kapitan"' : '',
                ]);
                $tpl->parse('tymy.skupina.tym.clenove.clen');
                $maily[] = $clen->mail();
            }
            if ($maily) {
                $tpl->assign('maily', implode('; ', $maily));
                $tpl->parse('tymy.skupina.tym.clenove');
            }

            $smíZamykat = $u->jeSefInfopultu();
            $tpl->assign([
                'zamceni_name'     => $aktivitaTym->jeZamceny() ? 'odemknoutTym' : 'zamknoutTym',
                'zamceni_label'    => $aktivitaTym->jeZamceny() ? 'Odemknout tým' : 'Zamknout tým',
                'zamceni_disabled' => $smíZamykat ? '' : 'disabled',
                'zamceni_title'    => $smíZamykat ? '' : 'Zamykání/odemykání týmu může provádět pouze Šéf infopultu',
            ]);
            $tpl->parse('tymy.skupina.tym.zamceniTlacitko');

            $tpl->parse('tymy.skupina.tym');
        }
    }

    $tpl->parse('tymy.skupina');
}

$tpl->parse('tymy');
$tpl->out('tymy');
