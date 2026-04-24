<?php

use App\Service\AktivitaTymService;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\Program;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Pravo;

/** @var Modul $this */
/** @var \Gamecon\XTemplate\XTemplate $t */
/** @var Uzivatel $u */
/** @var Uzivatel $uPracovni */
/** @var Url $url */
/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$this->blackarrowStyl(true);

$dny = [];
for ($den = new DateTimeCz(PROGRAM_OD); $den->pred(PROGRAM_DO); $den->plusDen()) {
    $dny[slugify($den->format('l'))] = clone $den;
}

// todo(tym): tohle půjde pryč
$nastaveni       = [];
$alternativniUrl = null;
$title           = 'Program';
// TODO: přesunout logiku práce s URL za program/ do preactu
if ($url->cast(1) === 'muj') {
    $nastaveni[Program::OSOBNI] = true;
} else if (isset($dny[$url->cast(1)])) {
    $nastaveni[Program::DEN] = $dny[$url->cast(1)]->format('z');
} else if (!$url->cast(1)) {
    $nastaveni[Program::DEN] = reset($dny)->format('z');
    $alternativniUrl         = 'program/' . slugify(reset($dny)->format('l'));
} else {
    throw new Nenalezeno();
}

$program = new Program($systemoveNastaveni, $u, $nastaveni);
Aktivita::prihlasovatkoZpracuj($u, $u);

$this->info()->nazev($program->titulek($url->cast(1)));

foreach ($program->cssUrls() as $cssUrl) {
    $this->pridejCssUrl($cssUrl);
}

// foreach ($program->jsModulyUrls() as $jsModulUrl) {
//     $this->pridejJsSoubor($jsModulUrl);
// }

$zacatekPristiVlnyOd       = $systemoveNastaveni->pristiVlnaKdy();
$zacatekPristiVlnyZaSekund = $zacatekPristiVlnyOd !== null
    ? $zacatekPristiVlnyOd->getTimestamp() - $systemoveNastaveni->ted()->getTimestamp()
    : null;

$jeOrganizator = isset($u) && $u && $u->maPravoNaPoradaniAktivit();

?>

<style>
    /* na stránce programu nedělat sticky menu, aby bylo maximum místa pro progam */
    .menu {
        position: relative;
        /* relative, aby fungoval z-index */
    }
</style>


<?php $program->vypisPreact($uPracovni ?? $u) ?>

<div style="height: 70px"></div>
