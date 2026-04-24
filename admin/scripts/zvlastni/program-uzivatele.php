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

<?php $program->vypisPreact($uPracovni ?? $u, true); ?>

<?php profilInfo(); ?>

</body>
</html>
