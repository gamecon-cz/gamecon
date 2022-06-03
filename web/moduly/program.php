<?php

use \Gamecon\Cas\DateTimeCz;

/** @var Modul $this */
/** @var XTemplate $t */
/** @var Uzivatel $u */
/** @var url $url */

$this->blackarrowStyl(true);

$dny = [];
for ($den = new DateTimeCz(PROGRAM_OD); $den->pred(PROGRAM_DO); $den->plusDen()) {
    $dny[slugify($den->format('l'))] = clone $den;
}

$nastaveni = [];
$alternativniUrl = null;
$title = 'Program';

$this->info()->nazev($title);

$program = new Program($u, $nastaveni);
$program->zpracujPost();

$this->pridejCssUrl($program->cssUrl());
$this->pridejJsSoubor('soubory/blackarrow/program-nahled/program-nahled.js');
$this->pridejJsSoubor('soubory/blackarrow/_spolecne/zachovej-scroll.js');

$this->pridejCssUrl('soubory/ui/style.css');

$zacatekPrvniVlnyOd = \Gamecon\Cas\DateTimeGamecon::zacatekPrvniVlnyOd();
$zacatekPrvniVlnyZaSekund = $zacatekPrvniVlnyOd->getTimestamp() - time();

$legendaText = Stranka::zUrl('program-legenda-text')->html();
$jeOrganizator = isset($u) && $u && $u->maPravo(P_ORG_AKTIVIT);

$zobrazitMujProgramOdkaz = isset($u);

?>

<style>
    /* na stránce programu nedělat sticky menu, aby bylo maximum místa pro progam */
    .menu {
        position: relative; /* relative, aby fungoval z-index */
    }
</style>


<div id="preact-program">Loading...</div>


<div style="height: 70px"></div>

<script type="text/javascript">
    programNahled(
        document.querySelector('.programNahled_obalNahledu'),
        document.querySelector('.programNahled_obalProgramu'),
        document.querySelectorAll('.programNahled_odkaz'),
        document.querySelectorAll('.program form > a'),
    )

    // TODO:
    /* 
    zachovejScroll(
        document.querySelectorAll('.program form > a'),
        document.querySelector('.programPosuv_obal'),
        )
    */

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

<script>
    // Konstanty předáváné do Preactu (env.ts)
    window.GAMECON_KONSTANTY = {
        BASE_PATH_API: "/web/api/",
        BASE_PATH_PAGE: "/web/program/",
        ROK: <?= ROK ?>,
        PROGRAM_OD: <?= (new DateTime(PROGRAM_OD))->getTimestamp() * 1000 ?>,
        PROGRAM_DO: <?= (new DateTime(PROGRAM_DO))->getTimestamp() * 1000 ?>,
        LEGENDA: `<?= substr($legendaText,0,-1) ?>`,
    }
</script>
<script type="module" src="<?= $this->zabalJsSoubor('soubory/ui/bundle.js') ?>"></script>
