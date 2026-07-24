<?php

declare(strict_types=1);

use Gamecon\Aktivita\Aktivita;
use Gamecon\Cas\DateTimeCz;
use Gamecon\XTemplate\XTemplate;

$xtpl = new XTemplate(__DIR__ . '/last-minute-tabule.xtpl');

$datum = (int) date('Y') !== (int) ROCNIK // fix pro datum ze špatných let
    ? new DateTimeImmutable(ROCNIK . '-01-01 01:00')
    : new DateTimeImmutable();
$od = $datum->sub(new DateInterval('PT15M'));
$do = (int) $datum->format('G') < 20
    ? $datum->add(new DateInterval('PT3H45M')) // před 20:00 vypisovat 4h dopředu, potom už další den
    : $datum->add(new DateInterval('P1D'))->setTime(9, 0);
$denPredchozihoBloku = null;
$zacatekPrvniAktivityBloku = null;
$zitraBloku = null;
$aktivity = Aktivita::zRozmezi(
    $od,
    $do,
    Aktivita::JEN_VOLNE | Aktivita::VEREJNE | Aktivita::NEUZAVRENE,
);
usort($aktivity, static function (Aktivita $nejakaAktivita, Aktivita $dalsiAktivita) {
    $i = $nejakaAktivita->zacatek() <=> $dalsiAktivita->zacatek();
    if ($i === 0) {
        $zaplnenost1 = $nejakaAktivita->pocetPrihlasenych() / ($nejakaAktivita->kapacita() === 0 ? 1000 : $nejakaAktivita->kapacita());
        $zaplnenost2 = $dalsiAktivita->pocetPrihlasenych() / ($dalsiAktivita->kapacita() === 0 ? 1000 : $dalsiAktivita->kapacita());

        return -($zaplnenost1 <=> $zaplnenost2);
    }

    return $i;
});
foreach ($aktivity as $aktivita) {
    if (! $aktivita->prihlasovatelna() || $aktivita->jeToDalsiKolo()) {
        continue;
    }
    if ($denPredchozihoBloku !== null && $denPredchozihoBloku !== $aktivita->zacatek()->format('z')) {
        // den se změnil → uzavřít předchozí blok s jeho vlastním nadpisem
        $xtpl->assign('cas', $zacatekPrvniAktivityBloku);
        $xtpl->assign('zitra', $zitraBloku);
        $xtpl->parse('tabule.blok');
        $zacatekPrvniAktivityBloku = null;
    }
    if ($zacatekPrvniAktivityBloku === null) {
        $zacatekPrvniAktivityBloku = $aktivita->zacatek()->format('G:i');
        $zitraBloku = $aktivita->zacatek()->rozdilDni($od);
    }
    $kapacita = $aktivita->kapacita();
    $zbyva = $kapacita - $aktivita->pocetPrihlasenych();
    $skoroPlno = $kapacita > 0 && ($zbyva <= 2 || $aktivita->pocetPrihlasenych() / $kapacita >= 0.9);
    $xtpl->assign([
        'nazev'       => $aktivita->nazev(),
        'obsazenost'  => str_replace(['(', ')'], '', $aktivita->obsazenostHtml()),
        'zacatek'     => $aktivita->zacatek()->format('G:i'),
        'plnostTrida' => $skoroPlno ? 'aktivita--skoroPlno' : '',
        'stitek'      => $skoroPlno
            ? '<span class="stitek">' . ($zbyva === 1 ? 'poslední místo' : 'poslední místa') . '</span>'
            : '',
    ]);
    $xtpl->parse('tabule.blok.aktivita');
    $denPredchozihoBloku = $aktivita->zacatek()->format('z');
}
if ($denPredchozihoBloku === null) {
    $xtpl->assign('cas', DateTimeCz::createFromInterface($od)->zaokrouhlitNaHodinyNahoru()->format('G:i'));
    $xtpl->assign('zitra', '');
    $xtpl->parse('tabule.blok.nic');
} else {
    $xtpl->assign('cas', $zacatekPrvniAktivityBloku);
    $xtpl->assign('zitra', $zitraBloku);
}
$xtpl->parse('tabule.blok');

$zoom = empty($_GET['zoom']) ? 100 : (int) $_GET['zoom'];
$xtpl->assign('lupa', $zoom);
$xtpl->assign('lupaPlus', $zoom + 10);
$xtpl->assign('lupaMinus', $zoom - 10);

$xtpl->assign('urlWebu', rtrim(URL_WEBU, '/'));
$xtpl->assign('ted', $datum->format('G:i'));
$xtpl->parse('tabule');
$xtpl->out('tabule');
