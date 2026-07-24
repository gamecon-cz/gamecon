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
foreach ($aktivity as $a) {
    if (! $a->prihlasovatelna() || $a->jeToDalsiKolo()) {
        continue;
    }
    if ($denPredchozihoBloku !== null && $denPredchozihoBloku !== $a->zacatek()->format('z')) {
        // den se změnil → uzavřít předchozí blok s jeho vlastním nadpisem
        $xtpl->assign('cas', $zacatekPrvniAktivityBloku);
        $xtpl->assign('zitra', $zitraBloku);
        $xtpl->parse('tabule.blok');
        $zacatekPrvniAktivityBloku = null;
    }
    if ($zacatekPrvniAktivityBloku === null) {
        $zacatekPrvniAktivityBloku = $a->zacatek()->format('G:i');
        $zitraBloku = $a->zacatek()->rozdilDni($od);
    }
    $xtpl->assign([
        'nazev'      => $a->nazev(),
        'obsazenost' => $a->obsazenostHtml(),
        'zacatek'    => $a->zacatek()->format('G:i'),
    ]);
    $xtpl->parse('tabule.blok.aktivita');
    $denPredchozihoBloku = $a->zacatek()->format('z');
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

$xtpl->assign('programCss', '');
$xtpl->parse('tabule');
$xtpl->out('tabule');
