<?php

require_once('../../'.$SDILENE_SLOZKA.'program.hhp');

$program=new Program();

$zoom=empty($_GET['zoom'])?100:(int)$_GET['zoom'];

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="cs" lang="cs">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <?php $program->css(); ?>
    <style>
      body { 
        font-family: tahoma;
        font-size: 11px;
        text-align: center;
        background-color: #f0f0f0; }
      table.program { min-width: 0; zoom: <?=$zoom?>%; }
      .lupa { display: block; width: 48px; height: 48px; position: absolute; background-size: 100%; opacity: 0.2; }
      .lupa.plus { background-image: url('/files/design/lupa-plus.png'); }
      .lupa.minus { background-image: url('/files/design/lupa-minus.png'); }
      .lupa:hover { opacity:1.0; }
    </style>
    <meta http-equiv="refresh" content="30">
  </head>
  <body>
  
  <a href="?zoom=<?=$zoom+10?>" class="lupa plus" style="top:0;left:0"></a>
  <a href="?zoom=<?=$zoom-10?>" class="lupa minus" style="top:48px;left:0"></a>
  
  <?php $program->tisk(); ?>
  
  <?php /* profilInfo(); */ ?>
  
  </body>
</html>
