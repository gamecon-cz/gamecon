<?php

use \Gamecon\Cas\DateTimeCz;

/** @var Modul $this */
/** @var XTemplate $t */
/** @var Uzivatel $u */
/** @var url $url */

$this->blackarrowStyl(true);
$this->info()->nazev('Program');

$dny = [];
for ($den = new DateTimeCz(PROGRAM_OD); $den->pred(PROGRAM_DO); $den->plusDen()) {
    $dny[slugify($den->format('l'))] = clone $den;
}

$nastaveni = [];
$alternativniUrl = null;
if ($url->cast(1) === 'muj') {
    if (!$u) {
        throw new Neprihlasen();
    }
    $nastaveni['osobni'] = true;
} else if (isset($dny[$url->cast(1)])) {
    $nastaveni['den'] = $dny[$url->cast(1)]->format('z');
} else if (!$url->cast(1)) {
    $nastaveni['den'] = reset($dny)->format('z');
    $alternativniUrl = 'program/' . slugify(reset($dny)->format('l'));
} else {
    throw new Nenalezeno();
}

$program = new Program($u, $nastaveni);
$program->zpracujPost();

$this->pridejCssUrl($program->cssUrl());
$this->pridejJsSoubor('soubory/blackarrow/program-nahled/program-nahled.js');
$this->pridejJsSoubor('soubory/blackarrow/program-posuv/program-posuv.js');
$this->pridejJsSoubor('soubory/blackarrow/_spolecne/zachovej-scroll.js');

$zacatekPrvniVlnyOd = \Gamecon\Cas\DateTimeGamecon::zacatekPrvniVlnyOd();
$zacatekPrvniVlnyZaSekund = $zacatekPrvniVlnyOd->getTimestamp() - time();

$legenda = Stranka::zUrl('program-legenda')->html();
$legenda = str_replace('{a}', $u ? $u->koncA() : '', $legenda);
$legenda = str_replace('{n}', $u && $u->pohlavi() == 'f' ? 'ice' : 'ík', $legenda);
if (!isset($u) || !$u || !$u->maPravo(P_ORG_AKCI)) $legenda = preg_replace('@.*organizuji.*@', '', $legenda);

// pomocná funkce pro zobrazení aktivního odkazu
$aktivni = function ($urlOdkazu) use ($url, $alternativniUrl) {
    $cssTridy = 'program_den';

    if ($urlOdkazu == $url->cela() || $urlOdkazu == $alternativniUrl) {
        $cssTridy .= ' program_den-aktivni';
    }

    return 'href="' . $urlOdkazu . '" class="' . $cssTridy . '"';
};

$zobrazitMujProgramOdkaz = isset($u);

?>

<style>
    /* na stránce programu nedělat sticky menu, aby bylo maximum místa pro progam */
    .menu {
        position: relative; /* relative, aby fungoval z-index */
    }
</style>

<!-- relativní obal kvůli náhledu -->
<div style="position: relative">

    <?php require __DIR__ . '/../soubory/blackarrow/program-nahled/program-nahled.html'; ?>

    <div class="program_hlavicka">
        <?php if ($u) { ?>
            <!-- zatim nefunguje            <a href="program-k-tisku" class="program_tisk" target="_blank">Můj program v PDF</a>-->
        <?php } ?>
        <h1>Program <?= ROK ?></h1>
        <div class="program_dny">
            <?php foreach ($dny as $denSlug => $den) { ?>
                <a <?= $aktivni('program/' . $denSlug) ?>><?= $den->format('l d.n.') ?></a>
            <?php } ?>
            <?php if ($zobrazitMujProgramOdkaz) { ?>
                <a <?= $aktivni('program/muj') ?>>můj program</a>
            <?php } ?>
        </div>
    </div>

    <div class="program_legenda"><?= $legenda ?></div>

    <div class="programNahled_obalProgramu">
        <div class="programPosuv_obal2">
            <div class="programPosuv_obal">
                <?php $program->tisk(); ?>
            </div>
        </div>
    </div>

</div>

<div style="height: 70px"></div>

<script type="text/javascript">
    programNahled(
        document.querySelector('.programNahled_obalNahledu'),
        document.querySelector('.programNahled_obalProgramu'),
        document.querySelectorAll('.programNahled_odkaz'),
        document.querySelectorAll('.program form > a'),
    )

    zachovejScroll(
        document.querySelectorAll('.program form > a'),
        document.querySelector('.programPosuv_obal'),
    )

    programPosuv(document.querySelector('.programPosuv_obal2'))

    <?php if ($zacatekPrvniVlnyZaSekund > 0) { ?>
    setTimeout(function () {
        location.reload()
    }, <?= $zacatekPrvniVlnyZaSekund * 1000 ?>)
    <?php } ?>
</script>
