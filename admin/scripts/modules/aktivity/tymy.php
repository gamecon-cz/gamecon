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

$tpl = new XTemplate(__DIR__ . '/tymy.xtpl');

// Zpracování POST akce: rozebrat tým
if (post('rozebratTym')) {
    $kodTymu = (int)post('kodTymu');
    $idAkce  = (int)post('idAkce');
    if ($kodTymu > 0 && $idAkce > 0) {
        AktivitaTym::rozebratTym($kodTymu, $idAkce);
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Všechny teamové aktivity ročníku
$aktivity = dbQuery(<<<SQL
    SELECT id_akce, nazev_akce, team_min, team_max, team_kapacita
    FROM akce_seznam
    WHERE rok = $0
      AND teamova = 1
    ORDER BY zacatek, nazev_akce
    SQL,
    [0 => ROCNIK],
);

while ($aktivitaRow = mysqli_fetch_assoc($aktivity)) {
    $idAkce = (int)$aktivitaRow['id_akce'];

    $vsechnyTymy       = AktivitaTym::vsechnyTymy($idAkce);
    $pocetTymu         = count($vsechnyTymy);
    $pocetClenuvCelkem = array_sum(array_map(fn($tym) => $tym->pocetClenu, $vsechnyTymy));

    $kapacitaTymu = $aktivitaRow['team_kapacita'] !== null
        ? $pocetTymu . '/' . $aktivitaRow['team_kapacita']
        : (string)$pocetTymu;

    $minMax = '';
    if ($aktivitaRow['team_min'] !== null || $aktivitaRow['team_max'] !== null) {
        $minMax = ' &nbsp;|&nbsp; členů na tým: ' . ($aktivitaRow['team_min'] ?? '?') . '–' . ($aktivitaRow['team_max'] ?? '∞');
    }

    $tpl->assign([
        'nazevAktivity'     => $aktivitaRow['nazev_akce'],
        'kapacitaTymu'      => $kapacitaTymu,
        'pocetClenuvCelkem' => $pocetClenuvCelkem,
        'minMaxClenu'       => $minMax,
    ]);

    if ($vsechnyTymy === []) {
        $tpl->parse('tymy.aktivita.zadneTymy');
    } else {
        foreach ($vsechnyTymy as $tym) {
            $kapitan    = Uzivatel::zId($tym->idKapitana);
            $obsazenost = $tym->pocetClenu . '/' . ($tym->limit ?? '∞');
            $zalozen    = $tym->zalozen
                ? (new DateTimeCz($tym->zalozen->format('Y-m-d H:i:s')))->format('j.n. H:i')
                : '–';

            $tpl->assign([
                'kod'        => $tym->kod,
                'nazev'      => $tym->nazev ?: '(bez názvu)',
                'kapitan'    => $kapitan->login() . ' (' . $kapitan->krestniJmeno() . ' ' . $kapitan->prijmeni() . ')',
                'obsazenost' => $obsazenost,
                'verejny'    => $tym->verejny ? 'veřejný' : 'soukromý',
                'zalozen'    => $zalozen,
                'id_akce'    => $idAkce,
            ]);

            $aktivitaTym = AktivitaTym::najdiPodleKodu($idAkce, $tym->kod);
            $clenove     = $aktivitaTym->clenoveTymu();
            $maily       = [];
            foreach ($clenove as $clen) {
                $jeKapitan = $clen->id() === $tym->idKapitana;
                $tpl->assign([
                    'nick'       => $clen->login(),
                    'jmeno'      => $clen->krestniJmeno() . ' ' . $clen->prijmeni(),
                    'mail'       => $clen->mail(),
                    'je_kapitan' => $jeKapitan ? ' (kapitán)' : '',
                    'trida'      => $jeKapitan ? ' class="kapitan"' : '',
                ]);
                $tpl->parse('tymy.aktivita.tym.clenove.clen');
                $maily[] = $clen->mail();
            }
            if ($maily) {
                $tpl->assign('maily', implode('; ', $maily));
                $tpl->parse('tymy.aktivita.tym.clenove');
            }

            $tpl->parse('tymy.aktivita.tym');
        }
    }

    $tpl->parse('tymy.aktivita');
}

$tpl->parse('tymy');
$tpl->out('tymy');
