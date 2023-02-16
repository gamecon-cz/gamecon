<?php

use Gamecon\Cas\DateTimeCz;
use Gamecon\Pravo;

/** @var Modul $this */
/** @var \Gamecon\XTemplate\XTemplate $t */
/** @var Uzivatel $u */
/** @var url $url */

$this->blackarrowStyl(true);

$dny = [];
for ($den = new DateTimeCz(PROGRAM_OD); $den->pred(PROGRAM_DO); $den->plusDen()) {
    $dny[slugify($den->format('l'))] = clone $den;
}

$nastaveni       = [];
$alternativniUrl = null;
$title           = 'Program';
if ($url->cast(1) === 'muj') {
    if (!$u) {
        throw new Neprihlasen();
    }
    $nastaveni[Program::OSOBNI] = true;
    $title                      = 'Můj program';
} else if (isset($dny[$url->cast(1)])) {
    $nastaveni[Program::DEN] = $dny[$url->cast(1)]->format('z');
    $title                   = 'Program ' . $dny[$url->cast(1)]->format('l');
} else if (!$url->cast(1)) {
    $nastaveni[Program::DEN] = reset($dny)->format('z');
    $alternativniUrl         = 'program/' . slugify(reset($dny)->format('l'));
    $title                   = 'Program ' . reset($dny)->format('l');
} else {
    throw new Nenalezeno();
}

$this->info()->nazev($title);

$program = new Program($u, $nastaveni);
$program->zpracujPost($u);

foreach ($program->cssUrls() as $cssUrl) {
    $this->pridejCssUrl($cssUrl);
}
$this->pridejJsSoubor(__DIR__ . '/../soubory/blackarrow/program-nahled/program-nahled.js');
$this->pridejJsSoubor(__DIR__ . '/../soubory/blackarrow/program-posuv/program-posuv.js');
$this->pridejJsSoubor(__DIR__ . '/../soubory/blackarrow/_spolecne/zachovej-scroll.js');

$zacatekPrvniVlnyOd       = \Gamecon\Cas\DateTimeGamecon::zacatekPrvniVlnyOd();
$zacatekPrvniVlnyZaSekund = $zacatekPrvniVlnyOd->getTimestamp() - time();

$legendaText   = Stranka::zUrl('program-legenda-text')->html();
$jeOrganizator = isset($u) && $u && $u->maPravo(Pravo::PORADANI_AKTIVIT);

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
        <h1>Program <?= ROCNIK ?></h1>
        <div class="program_dny">
            <?php foreach ($dny as $denSlug => $den) { ?>
                <a <?= $aktivni('program/' . $denSlug) ?>><?= $den->format('l d.n.') ?></a>
            <?php } ?>
            <?php if ($zobrazitMujProgramOdkaz) { ?>
                <a <?= $aktivni('program/muj') ?>>můj program</a>
            <?php } ?>
        </div>
    </div>

    <div class="program_legenda">

        <div class="informaceSpustime"><?= $legendaText ?></div>

        <div class="program_legenda_inner">
            <span class="program_legenda_typ">Otevřené</span>
            <span class="program_legenda_typ vDalsiVlne">V další vlně</span>
            <span class="program_legenda_typ vBudoucnu">Připravujeme</span>
            <span class="program_legenda_typ sledujici">Sleduji</span>
            <span class="program_legenda_typ prihlasen">Přihlášen<?= $u ? $u->koncovkaDlePohlavi() : '' ?></span>
            <span class="program_legenda_typ plno">Plno</span>
            <?php if ($jeOrganizator) { ?>
                <span class="program_legenda_typ organizator">organizuji</span>
            <?php } ?>
        </div>
    </div>


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

    <?php if ($zacatekPrvniVlnyZaSekund > 0) {
    $zacatekPrvniVlnyZaMilisekund = $zacatekPrvniVlnyZaSekund * 1000;
    if ($zacatekPrvniVlnyZaMilisekund > 0) { ?> /*kdyby to náhodou přeteklo za 2^32 -1 */
    if (<?= $zacatekPrvniVlnyZaMilisekund ?> <= 2147483647) {
        setTimeout(function () {
            location.reload()
        }, <?= $zacatekPrvniVlnyZaMilisekund ?>)
    }
    <?php }
    } ?>
</script>
