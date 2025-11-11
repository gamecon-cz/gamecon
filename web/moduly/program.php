<?php

use Gamecon\Aktivita\Program;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Pravo;

/** @var Modul $this */
/** @var \Gamecon\XTemplate\XTemplate $t */
/** @var Uzivatel $u */
/** @var Url $url */
/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$this->blackarrowStyl(true);

$dny = [];
for ($den = new DateTimeCz(PROGRAM_OD); $den->pred(PROGRAM_DO); $den->plusDen()) {
    $dny[slugify($den->format('l'))] = clone $den;
}

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
$program->zpracujPost($u);

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

$legendaText   = Stranka::zUrl('program-legenda-text')?->html();
$jeOrganizator = isset($u) && $u && $u->maPravo(Pravo::PORADANI_AKTIVIT);

?>

<style>
    /* na stránce programu nedělat sticky menu, aby bylo maximum místa pro progam */
    .menu {
        position: relative;
        /* relative, aby fungoval z-index */
    }
</style>


<?php
function zabalWebSoubor(string $cestaKSouboru): string
{
    return $cestaKSouboru . '?version=' . md5_file(WWW . '/' . $cestaKSouboru);
}

?>

<link rel="stylesheet" href="<?= zabalWebSoubor('soubory/ui/style.css') ?>">

<div id="preact-program">Program se načítá ...</div>
<script>
    // Konstanty předáváné do Preactu (env.ts)
    window.GAMECON_KONSTANTY = {
        BASE_PATH_API: "<?= URL_WEBU . "/api/" ?>",
        BASE_PATH_PAGE: "<?= URL_WEBU . "/program/" ?>",
        ROCNIK: <?= ROCNIK ?>,
        LEGENDA: <?= json_encode($legendaText) ?>,
        FORCE_REDUX_DEVTOOLS: <?= defined("FORCE_REDUX_DEVTOOLS") ? "true" : "false" ?>,
        PROGRAM_OD: <?= (new DateTimeCz(PROGRAM_OD))->getTimestamp() ?>000,
        PROGRAM_DO: <?= (new DateTimeCz(PROGRAM_DO))->getTimestamp() ?>000,
        PROGRAM_ZACATEK: <?= PROGRAM_ZACATEK ?>,
        PROGRAM_KONEC: <?= PROGRAM_KONEC ?>,
    }

    window.gameconPřednačtení =
    <?php
    $res = [];
    if ($u) {
        $res["prihlasen"]          = true;
        $res["pohlavi"]            = $u->pohlavi();
        $res["koncovkaDlePohlavi"] = $u->koncovkaDlePohlavi();

        if ($u->jeOrganizator()) {
            $res["organizator"] = true;
        }
        if ($u->jeBrigadnik()) {
            $res["brigadnik"] = true;
        }

        $res["gcStav"] = "nepřihlášen";

        if ($u->gcPrihlasen()) {
            $res["gcStav"] = "přihlášen";
        }
        if ($u->gcPritomen()) {
            $res["gcStav"] = "přítomen";
        }
        if ($u->gcOdjel()) {
            $res["gcStav"] = "odjel";
        }
    }
    // TODO: použít jednu logiku stejně jako z API
    echo json_encode(["přihlášenýUživatel" => $res], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ?>
</script>

<script type="module" src="<?= zabalWebSoubor('soubory/ui/bundle.js') ?>"></script>

<div style="height: 70px"></div>
