<?php

use Gamecon\Aktivita\Program;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Pravo;

/** @var Modul $this */
/** @var \Gamecon\XTemplate\XTemplate $t */
/** @var Uzivatel $u */
/** @var url $url */
/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

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

$program = new Program($systemoveNastaveni, $u, $nastaveni);
$program->zpracujPost($u);

foreach ($program->cssUrls() as $cssUrl) {
    $this->pridejCssUrl($cssUrl);
}
$this->pridejCssSoubor(__DIR__ . '/../../web/soubory/blackarrow/jquery-multiselect/jquery.multiselect.gamecon.css');
$this->pridejCssSoubor(__DIR__ . '/../soubory/blackarrow/jquery-multiselect/jquery.multiselect-2.4.16.css');

$this->pridejJsSoubor(__DIR__ . '/../soubory/blackarrow/jquery-multiselect/jquery-3.4.1.min.js');
$this->pridejJsSoubor(__DIR__ . '/../soubory/blackarrow/jquery-multiselect/jquery.multiselect-2.4.16.gamecon.js');
$this->pridejJsSoubor(__DIR__ . '/../soubory/blackarrow/program-nahled/program-nahled.js');
$this->pridejJsSoubor(__DIR__ . '/../soubory/blackarrow/program-posuv/program-posuv.js');
$this->pridejJsSoubor(__DIR__ . '/../soubory/blackarrow/_spolecne/zachovej-scroll.js');
$this->pridejJsSoubor(__DIR__ . '/../soubory/blackarrow/program/filtr-programu.js');
$this->pridejJsSoubor(__DIR__ . '/../soubory/blackarrow/program/vyber-stitku.js');

$zacatekPristiVlnyOd       = $systemoveNastaveni->pristiVlnaKdy();
$zacatekPristiVlnyZaSekund = $zacatekPristiVlnyOd !== null
    ? $zacatekPristiVlnyOd->getTimestamp() - $systemoveNastaveni->ted()->getTimestamp()
    : null;

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

ob_start();
/** hack abychom měli nahrané tagy, @see \Gamecon\Aktivita\Program::tagyAktivit */
$program->tisk();
$programTisk = ob_get_clean();

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
            <span class="program_legenda_typ otevrene" data-typ-class="otevrene">
                <span class="before"></span>Otevřené
            </span>
            <span class="program_legenda_typ vDalsiVlne" data-typ-class="vDalsiVlne">
                <span class="before"></span>V další vlně
            </span>
            <span class="program_legenda_typ vBudoucnu" data-typ-class="vBudoucnu">
                <span class="before"></span>Připravujeme
            </span>
            <span class="program_legenda_typ sledujici" data-typ-class="sledujici">
                <span class="before"></span>Sleduji
            </span>
            <span class="program_legenda_typ prihlasen"
                  data-typ-class="prihlasen">
                <span class="before"></span>
                Přihlášen<?= $u ? $u->koncovkaDlePohlavi() : '' ?>
            </span>
            <span class="program_legenda_typ plno" data-typ-class="plno">
                <span class="before"></span>Plno
            </span>
            <?php if ($jeOrganizator) { ?>
                <span class="program_legenda_typ organizator" data-typ-class="organizator">
                    <span class="before"></span>organizuji
                </span>
            <?php } ?>
        </div>

        <div class="program_legenda_stitky">
            <select name="tag[]" multiple id="vyberStitkuProgram">
                <?php
                $tagy = $program->tagyAktivit();
                sort($tagy);
                foreach ($tagy as $tag) { ?>
                    <option class="program_legenda_tag" value="<?= $tag ?>"><?= $tag ?></option>
                <?php } ?>
            </select>
            <script type="text/javascript">
                document.dispatchEvent(new Event('stitkyNahrane'))
            </script>
        </div>
    </div>


    <div class="programNahled_obalProgramu">
        <div class="programPosuv_obal2">
            <div class="programPosuv_obal">
                <?= $programTisk; ?>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        document.dispatchEvent(new Event('programNacteny'))
    </script>

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

    <?php if ($zacatekPristiVlnyZaSekund !== null && $zacatekPristiVlnyZaSekund > 3) { // nebudeme auto-refreshovat lidem co mačkají F5
    $zacatekPristiVlnyZaMilisekund = $zacatekPristiVlnyZaSekund * 1000;
    /* protože by to mohlo přetéct 2^32 -1 */
    if ($zacatekPristiVlnyZaMilisekund <= 2147483647) { ?>
    setTimeout(function () {
        location.reload()
    }, <?= $zacatekPristiVlnyZaMilisekund + 2000 /* radši s rezervou, ať slavnostně neobnovíme stránku kde ještě nic není */ ?>)
    <?php }
    } ?>
</script>
