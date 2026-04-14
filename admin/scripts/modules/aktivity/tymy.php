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
        AktivitaTym::najdiPodleKodu($idAkce, $kodTymu)
            ->rozebratTym();
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

    $vsechnyTymyAktivity = AktivitaTym::vsechnyTymyAktivity($idAkce);
    $pocetTymu           = count($vsechnyTymyAktivity);
    $pocetClenuvCelkem   = array_sum(array_map(fn($tym) => $tym->pocetClenu, $vsechnyTymyAktivity));

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

    if ($vsechnyTymyAktivity === []) {
        $tpl->parse('tymy.aktivita.zadneTymy');
    } else {
        foreach ($vsechnyTymyAktivity as $aktivitaTym) {
            $idKapitana = $aktivitaTym->idKapitana();
            $kapitan    = Uzivatel::zId($idKapitana);
            $clenove    = $aktivitaTym->clenoveTymu();
            $obsazenost = count($clenove) . '/' . ($aktivitaTym->limitTymu() ?? '∞');
            $zalozen    = $aktivitaTym->casZalozeniMs()
                ? (new DateTimeCz((new \DateTime('@' . floor($aktivitaTym->casZalozeniMs() / 1000)))->format('Y-m-d H:i:s')))->format('j.n. H:i')
                : '–';

            $tpl->assign([
                'kod'        => $aktivitaTym->getKod(),
                'nazev'      => $aktivitaTym->getNazev() ?: '(bez názvu)',
                'kapitan'    => $kapitan->login() . ' (' . $kapitan->krestniJmeno() . ' ' . $kapitan->prijmeni() . ')',
                'obsazenost' => $obsazenost,
                'verejny'    => $aktivitaTym->isVerejny() ? 'veřejný' : 'soukromý',
                'zalozen'    => $zalozen,
                'id_akce'    => $idAkce,
            ]);

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
