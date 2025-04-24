<?php
// TODO: smazat tenhle file

use Gamecon\Aktivita\Program;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Cas\DateTimeGamecon;
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
if ($url->cast(1) === 'muj') {
    if (!$u) {
        throw new Neprihlasen();
    }
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
$this->pridejJsSoubor(__DIR__ . '/../soubory/blackarrow/program-nahled/program-nahled.js');
$this->pridejJsSoubor(__DIR__ . '/../soubory/blackarrow/program-posuv/program-posuv.js');
$this->pridejJsSoubor(__DIR__ . '/../soubory/blackarrow/_spolecne/zachovej-scroll.js');
$this->pridejJsSoubor(__DIR__ . '/../soubory/blackarrow/_spolecne/cookies-tools.js');
$this->pridejJsSoubor(__DIR__ . '/../soubory/blackarrow/program/filtr-programu.js');
$this->pridejJsSoubor(__DIR__ . '/../soubory/blackarrow/program/program-prepnuti.js');

$zacatekPristiVlnyOd       = $systemoveNastaveni->pristiVlnaKdy();
$zacatekPristiVlnyZaSekund = $zacatekPristiVlnyOd !== null
    ? $zacatekPristiVlnyOd->getTimestamp() - $systemoveNastaveni->ted()->getTimestamp()
    : null;

$legendaText   = Stranka::zUrl('program-legenda-text')?->html();
$jeOrganizator = isset($u) && $u && $u->maPravo(Pravo::PORADANI_AKTIVIT);

// pomocná funkce pro zobrazení aktivního odkazu
$aktivni = function (string $urlOdkazu) use ($url, $alternativniUrl, $program): string {
    $cssTridy[] = 'program_den';

    $id      = null;
    $titulek = null;
    $kodDne  = substr($urlOdkazu, strpos($urlOdkazu, '#') + 1);
    if ($kodDne) {
        $id      = "programDen-{$kodDne}";
        $titulek = $program->titulek($kodDne);
    }

    $urlOdkazuBezHashe = substr($urlOdkazu, 0, strpos($urlOdkazu, '#') ?: null);
    if ($urlOdkazuBezHashe == $url->cela() || $urlOdkazuBezHashe == $alternativniUrl) {
        $cssTridy[] = 'program_den-aktivni';
    }

    $html = 'href="' . $urlOdkazu . '" class="' . implode(' ', $cssTridy) . '"';
    if ($id) {
        $html .= " id='{$id}'";
    }
    if ($titulek) {
        $html .= " data-titulek='{$titulek}'";
    }
    return $html;
};

$zobrazitMujProgramOdkaz = isset($u);

ob_start();
?>

<!-- relativní obal kvůli náhledu -->
<div style="position: relative">

    <?php require __DIR__ . '/../soubory/blackarrow/program-nahled/program-nahled.html'; ?>

    <div class="program_hlavicka">
        <?php if ($u) { ?>
            <!-- zatim nefunguje            <a href="program-k-tisku" class="program_tisk" target="_blank">Můj program v PDF</a>-->
        <?php } ?>
        <h1>Program <?= $systemoveNastaveni->rocnik() ?></h1>
        <div class="program_dny">
            <?php foreach ($dny as $denSlug => $den) { ?>
                <a <?= $aktivni("program/{$denSlug}#{$denSlug}", $this->info()->titulek()) ?>><?= $den->format('l d.n.') ?></a>
            <?php } ?>
            <?php if ($zobrazitMujProgramOdkaz) { ?>
                <a <?= $aktivni('program/muj#muj', $this->info()->titulek()) ?>>můj program</a>
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
    </div>


    <div class="programNahled_obalProgramu">
        <link rel="stylesheet" href="soubory/bootstrap.5.1.3.css"/>
        <link rel="stylesheet" href="soubory/bootstrap.fix.css?version=1"/>
        <script type="text/javascript" src="soubory/bootstrap.bundle.5.1.3.js"></script>
        <div class="programPosuv_obal2">
            <div class="programPosuv_obal">
                <?php
                $ostatniMoznosti = [];
                if ($zobrazitMujProgramOdkaz) {
                    $ostatniMoznosti['muj'] = [Program::OSOBNI => true];
                }
                foreach ($dny as $denSlugZpracovavany => $denZpracovavany) {
                    $ostatniMoznosti[$denSlugZpracovavany] = [Program::DEN => $denZpracovavany->format('z')];
                }
                foreach ($ostatniMoznosti as $kodProgramu => $ostatniMoznost) {
                    $kodNastaveni     = key($ostatniMoznost);
                    $hodnotaNastaveni = reset($ostatniMoznost);
                    $htmlClass        = ($nastaveni[$kodNastaveni] ?? null) == $hodnotaNastaveni
                        ? 'program_den_detail-aktivni'
                        : '';
                    $program->prepniProgram($kodNastaveni, $hodnotaNastaveni);
                    echo "<div class='program_den_detail {$htmlClass}' id='programDenDetail-{$kodProgramu}'>";
                    $program->tisk();
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<style>
    /* na stránce programu nedělat sticky menu, aby bylo maximum místa pro progam */
    .menu {
        position: relative; /* relative, aby fungoval z-index */
    }
</style>

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

    Array.from(document.querySelectorAll('a.program_den')).forEach(function (zalozkaElement) {
        initializujProgramPrepnuti(zalozkaElement)
    })

    <?php if ($zacatekPristiVlnyZaSekund !== null && $zacatekPristiVlnyZaSekund > 3) { // nebudeme auto-refreshovat lidem co mačkají F5
    $zacatekPristiVlnyZaMilisekund = $zacatekPristiVlnyZaSekund * 1000;
    /* protože by to mohlo přetéct 2^32 -1 */
    if ($zacatekPristiVlnyZaMilisekund <= 2147483647) { ?>
    setTimeout(function () {
        location.reload()
    }, <?= $zacatekPristiVlnyZaMilisekund + 2000 /* radši s rezervou, ať slavnostně neobnovíme stránku kde ještě nic není */ ?>)
    <?php }
    } ?>

    document.dispatchEvent(new Event('programNacteny'))
</script>
