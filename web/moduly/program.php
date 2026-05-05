<?php

use Gamecon\Aktivita\Program;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Web\Info;

/** @var Modul $this */
/** @var Uzivatel $u */
/** @var Uzivatel $uPracovni */
/** @var Url $url */
/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$this->blackarrowStyl(true);

$uPracovni ??= $u;

// TODO: přesunout logiku práce s URL za program/ do preactu
function nazevStranky(?string $slug): string {
    global $systemoveNastaveni;
    $dny = [];
    foreach (DateTimeGamecon::dnyProgramu($systemoveNastaveni) as $den) {
        $dny[slugify($den->format('l'))] = $den;
    }

    if (!$slug) {
        return 'Program ' . reset($dny)->format('l');
    }

    if ($slug === 'muj') {
        return 'Můj program';
    }
    if (isset($dny[$slug])) {
        return 'Program ' . $dny[$slug]->format('l');
    }

    throw new \Nenalezeno();
}

function titulek(?string $slug): string {
    global $systemoveNastaveni;
    return (new Info($systemoveNastaveni))->nazev(nazevStranky($slug))->titulek();
}

$this->info()->nazev(titulek($url->cast(1)));

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


<?php Program::vypisPreact() ?>

<div style="height: 70px"></div>
