<?php

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
        Program::MODERNI_ADMIN_LAYOUT => true,
        Program::TEAM_VYBER   => true,
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
    Aktivita::vyberTeamuZpracuj($uPracovni, $u);
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

<div class="program_admin_panel">
        <input type="button" value="Zavřít" class="program_admin_panel_zavrit"
           onclick="window.location = '<?= $u ? $u->mimoMojeAktivityUvodniAdminLink()['url'] : URL_ADMIN . '/uzivatel' ?>'"
        >
        <div class="program_admin_panel_jmeno"><?= $uPracovni->jmenoNick() ?></div>
        <div class="program_admin_panel_stav"><span id="stavUctu"><?= $uPracovni->finance()->formatovanyStav() ?></span></div>
        <div class="program_admin_panel_nav">
                <a href="program-uzivatele" class="program-odkaz">Program</a> |
                <a href="program-osobni" class="program-odkaz">Program účastníka</a>
        </div>
</div>

<div class="program_admin_page">
    <?= $chyba ?>
    <?php $program->tisk(); ?>
</div>

<script>
    (() => {
        scrollObnov()
        document.querySelectorAll('.program_admin_page form > a').forEach(e => {
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
