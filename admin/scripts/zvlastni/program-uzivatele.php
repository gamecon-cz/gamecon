<?php

if(!$uPracovni) {
  echo 'Není vybrán uživatel.';
  return;
}

$osobniProgram = isset($osobniProgram) ? (bool)$osobniProgram : false;

$program = new Program($uPracovni, [
  'drdPj'       => true,
  'drdPrihlas'  => true,
  'plusMinus'   => true,
  'osobni'      => $osobniProgram,
  'teamVyber'   => true,
  'technicke'   => true,
  'zpetne'      => $u->maPravo(P_ZMENA_HISTORIE),
]);

if($uPracovni) {
  Aktivita::prihlasovatkoZpracuj($uPracovni,
    Aktivita::PLUSMINUS_KAZDY |
    ($u->maPravo(P_ZMENA_HISTORIE) ? Aktivita::ZPETNE : 0) |
    Aktivita::TECHNICKE
  );
  Aktivita::vyberTeamuZpracuj($uPracovni);
}

$chyba = chyba::vyzvedniHtml();

?>
<!DOCTYPE html>
<html lang="cs" dir="ltr">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <script src="files/jquery-3.4.1.min.js"></script>
    <script src="files/jquery-ui-v1.12.  <?=$chyba?>
1.min.js"></script>
    <script src="files/program-ajax.js?version=6bd8244fd9a9874ea2703cdea497877b"></script>
    <base href="<?=URL_ADMIN?>/">
    <?php $program->css(); ?>
    <style>
      body {
        font-family: tahoma, sans, serif;
        font-size: 11px;
        text-align: center;
        background-color: #f0f0f0;
        overflow-y: scroll;
      }
      .program-odkaz {
        color: #fff;
      }
      .program h2:first-child {
        margin-top: 0;
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
  ">
    <input type="button" value="Zavřít" onclick="window.location = '<?=URL_ADMIN?>/uvod'" style="
      float: right;
      width: 100px;
      height: 35px;
    ">
    <?=$uPracovni->jmenoNick()?><br>
    <span id="stavUctu"><?=$uPracovni->finance()->stavHr()?></span><br>

    <a href="program-uzivatele" class="program-odkaz">Program účastníka</a> |
    <a href="program-osobni" class="program-odkaz">Filtrovaný program</a>
  </div>

  <div class="program">
    <?=$chyba?>
    <?php $program->tisk(); ?>
  </div>

  <?php profilInfo(); ?>

  </body>
</html>
