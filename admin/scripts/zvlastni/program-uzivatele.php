<?php

/** @var Uzivatel $u */
/** @var Uzivatel|null $uPracovni */

if (!$uPracovni) {
    echo 'Není vybrán uživatel.';
    return;
}

$osobniProgram = !empty($osobniProgram);

$program = new Program($uPracovni, [
    'drdPj' => true,
    'drdPrihlas' => true,
    'plusMinus' => true,
    'osobni' => $osobniProgram,
    'teamVyber' => true,
    'technicke' => true,
    'zpetne' => $u->maPravo(P_ZMENA_HISTORIE),
]);

if ($uPracovni) {
    Aktivita::prihlasovatkoZpracuj(
        $uPracovni,
        Aktivita::PLUSMINUS_KAZDY | ($u->maPravo(P_ZMENA_HISTORIE) ? Aktivita::ZPETNE : 0) | Aktivita::TECHNICKE
    );
    Aktivita::vyberTeamuZpracuj($uPracovni);
}

$chyba = Chyba::vyzvedniHtml();

?>
<!DOCTYPE html>
<html>
<head>
    <!-- jquery kvůli týmovým formulářům -->
    <script src="files/jquery-3.4.1.min.js"></script>
    <script src="files/jquery-ui-1.10.3.custom.min.js"></script>
    <script src="files/jquery-migrate-3.1.0.js"></script>
    <base href="<?= URL_ADMIN ?>/">
    <link rel="stylesheet" href="<?= $program->cssUrl() ?>">
    <style>
        body {
            font-family: tahoma, sans;
            font-size: 11px;
            line-height: 1.2;
            background-color: #fff;
            overflow-y: scroll;
        }

        .program-odkaz {
            color: #fff;
        }
    </style>
    <link rel="stylesheet" href="files/design/ui-lightness/jquery-ui-1.10.3.custom.min.css">
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
    <input type="button" value="Zavřít" onclick="window.location = '<?= URL_ADMIN ?>/uvod'" style="
      float: right;
      width: 100px;
      height: 35px;
    ">
    <?= $uPracovni->jmenoNick() ?><br>
    <span id="stavUctu"><?= $uPracovni->finance()->stavHr() ?></span><br>

    <a href="program-uzivatele" class="program-odkaz">Program účastníka</a> |
    <a href="program-osobni" class="program-odkaz">Filtrovaný program</a>
</div>

<div class="program">
    <?= $chyba ?>
    <?php $program->tisk(); ?>
</div>

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
