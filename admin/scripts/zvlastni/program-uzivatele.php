<?php

$osobniProgram=isset($osobniProgram)?$osobniProgram:false;

$program=new Program($uPracovni, $osobniProgram);
if($uPracovni) Aktivita::prihlasovatkoZpracuj($uPracovni);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="cs" lang="cs">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <base href="<?=URL_ADMIN?>/">
    <?php $program->css(); ?>
    <style>
      body { 
        font-family: tahoma;
        font-size: 11px;
        text-align: center;
        background-color: #f0f0f0; 
        overflow-y: scroll; }
    </style>
  </head>
  <body>
  
  <div style="text-align:left;max-width:1060px;margin:auto;font-size:16px;margin-bottom:-30px;margin-top:10px">
    <input type="button" style="position:absolute;margin: 10px 0 0 260px;width:100px;height:40px" value="Zavřít" onclick="window.close();">
    <?=$uPracovni->jmenoNick()?><br>
    <?=$uPracovni->finance()->stavHr()?><br>
    <?php if($osobniProgram){ ?>
      <a href="program-uzivatele">celkový program</a> osobní program
    <?php }else{ ?>
       celkový program <a href="program-osobni">osobní program</a>
    <?php } ?>
  </div>
  
  <?php $program->tisk(); ?>
  
  <?php profilInfo(); ?>
  
  </body>
</html>
