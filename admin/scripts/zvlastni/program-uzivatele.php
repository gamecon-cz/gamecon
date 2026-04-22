<?php

use App\Service\AktivitaTymService;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\Program;

/** @var Uzivatel $u */
/** @var Uzivatel|null $uPracovni */
/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

if (!$uPracovni) {
    echo 'Není vybrán uživatel.';
    return;
}

$osobniProgram = !empty($osobniProgram);

$program = new Program(
    systemoveNastaveni: $systemoveNastaveni,
    uzivatel: $uPracovni,
    nastaveni: [
        Program::DRD_PJ       => true,
        Program::DRD_PRIHLAS  => true,
        Program::PLUS_MINUS   => true,
        Program::OSOBNI       => $osobniProgram,
        Program::INTERNI      => true,
        Program::ZPETNE       => $u->maPravoNaZmenuHistorieAktivit(),
        Program::NEOTEVRENE   => $u->maPravoNaPrihlasovaniNaDosudNeotevrene(),
    ]
);

if ($uPracovni) {
    Aktivita::prihlasovatkoZpracuj(
        $uPracovni,
        $u,
        Aktivita::PLUSMINUS_KAZDY
        | ($u->maPravoNaZmenuHistorieAktivit() ? Aktivita::ZPETNE : 0)
        | Aktivita::INTERNI,
    );
}

$chyba = Chyba::vyzvedniHtml();

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <title>Program <?= htmlspecialchars($uPracovni->jmenoNick(), ENT_QUOTES | ENT_HTML5) ?></title>
    <!-- jquery kvůli týmovým formulářům -->
    <script src="files/jquery-3.4.1.min.js"></script>
    <script src="files/jquery-ui-v1.12.1.min.js"></script>
    <base href="<?= URL_ADMIN ?>/">
    <?php foreach ($program->cssUrls() as $cssUrl) { ?>
        <link rel="stylesheet" href="<?= $cssUrl ?>">
    <?php } ?>
    <link rel="stylesheet" href="files/design/hint.css">
    <style>
        body {
            font-family: tahoma, sans, sans-serif;
            font-size: 11px;
            line-height: 1.2;
            background-color: #fff;
            overflow-y: scroll;
        }

        .program-odkaz {
            color: #fff;
        }
    </style>
    <link rel="stylesheet" href="files/design/ui-lightness/jquery-ui-v1.12.1.min.css">
</head>
<body>

<div style="
    text-align: left;
    font-size: 16px;
    position: -webkit-sticky;
    position: sticky;
    top: 0; left: 0;
    width: 350px;
    padding: 10px;
    color: #fff;
    background-color: rgba(0,0,0,0.8);
    border-bottom-right-radius: 12px;
    z-index: 20;
  ">
    <input type="button" value="Zavřít"
           onclick="window.location = '<?= $u ? $u->mimoMojeAktivityUvodniAdminLink()['url'] : URL_ADMIN . '/uzivatel' ?>'"
           style="
      float: right;
      width: 100px;
      height: 35px;
    ">
    <?= $uPracovni->jmenoNick() ?><br>
    <span id="stavUctu"><?= $uPracovni->finance()->formatovanyStav() ?></span><br>

    <a href="program-uzivatele" class="program-odkaz">Program</a> |
    <a href="program-osobni" class="program-odkaz">Program účastníka</a>
</div>

<div class="program">
    <?= $chyba ?>
    <?php $program->tisk(); ?>
</div>

<?php
function zabalAdminSoubor(string $cestaKSouboru): string
{
    return $cestaKSouboru . '?version=' . md5_file(ADMIN . '/' . $cestaKSouboru);
}
?>

<link rel="stylesheet" href="<?= zabalAdminSoubor('files/ui/style.css') ?>">

<div id="preact-program">Program se načítá ...</div>

<script>
    // todo(tym): přihlašování odhlašování atd. se má dít pro aktuálně vybraného uživatele ne pro přihlášeného orga.
    // todo(tym): jaké všechny featury musí admin program podporovat ?
    // todo: tady je trochu redundance s programem na webu
    window.GAMECON_KONSTANTY = {
        BASE_PATH_API: "<?= URL_ADMIN . '/api/' ?>",
        BASE_PATH_PAGE: "<?= URL_ADMIN . '/program-uzivatele/' ?>",
        ROCNIK: <?= ROCNIK ?>,
        LEGENDA: null,
        FORCE_REDUX_DEVTOOLS: false,
        PROGRAM_OD: <?= (new \Gamecon\Cas\DateTimeCz(PROGRAM_OD))->getTimestamp() ?>000,
        PROGRAM_DO: <?= (new \Gamecon\Cas\DateTimeCz(PROGRAM_DO))->getTimestamp() ?>000,
        PROGRAM_ZACATEK: <?= PROGRAM_ZACATEK ?>,
        PROGRAM_KONEC: <?= PROGRAM_KONEC ?>,
        JE_ADMIN: true,
        CAS_NA_PRIPRAVENI_TYMU_MINUT: <?= AktivitaTymService::CAS_NA_PRIPRAVENI_TYMU_MINUT ?>,
    }

    window.gameconPřednačtení = <?php
    $res = [];
    if ($uPracovni) {
        $res['prihlasen']          = true;
        $res['pohlavi']            = $uPracovni->pohlavi();
        $res['koncovkaDlePohlavi'] = $uPracovni->koncovkaDlePohlavi();
        if ($uPracovni->jeOrganizator()) {
            $res['organizator'] = true;
        }
        if ($uPracovni->jeBrigadnik()) {
            $res['brigadnik'] = true;
        }
        $res['gcStav'] = 'nepřihlášen';
        if ($uPracovni->gcPrihlasen()) {
            $res['gcStav'] = 'přihlášen';
        }
        if ($uPracovni->gcPritomen()) {
            $res['gcStav'] = 'přítomen';
        }
        if ($uPracovni->gcOdjel()) {
            $res['gcStav'] = 'odjel';
        }
    }
    echo json_encode(['přihlášenýUživatel' => $res], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ?>
</script>

<script type="module" src="<?= zabalAdminSoubor('files/ui/bundle.js') ?>"></script>

<script>
    (() => {
        scrollObnov()
        document.querySelectorAll('.program form > a').forEach(e => {
            e.addEventListener('click', () => scrollUloz())
        })

        function scrollObnov() {
            let top = window.localStorage.getItem('scrollUloz_top')
            window.localStorage.removeItem('scrollUloz_top')
            let left = window.localStorage.getItem('scrollUloz_left')
            window.localStorage.removeItem('scrollUloz_left')
            if (top || left) {
                window.scrollTo({top: top, left: left})
            }
        }

        function scrollUloz() {
            window.localStorage.setItem('scrollUloz_top', window.scrollY)
            window.localStorage.setItem('scrollUloz_left', window.scrollX)
        }
    })()
</script>

<?php profilInfo(); ?>

</body>
</html>
