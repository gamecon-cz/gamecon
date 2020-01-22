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
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="cs" lang="cs">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <script src="files/jquery-2.1.0.min.js"></script>
    <script src="files/jquery-ui-1.10.3.custom.min.js"></script>
    <script src="files/program-ajax.js?version=6bd8244fd9a9874ea2703cdea497877b"></script>
    <base href="<?=URL_ADMIN?>/">
    <?php $program->css(); ?>
    <style>
      body {
        font-family: tahoma, sans;
        font-size: 11px;
        text-align: center;
        background-color: #f0f0f0;
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
    position: fixed;
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

  <?=$chyba?>

  <?php $program->tisk(); ?>

  <?php profilInfo(); ?>

  </body>
</html>
