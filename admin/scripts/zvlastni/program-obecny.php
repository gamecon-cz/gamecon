<?php

require_once('../../'.$SDILENE_SLOZKA.'program.hhp');

$program=new Program();

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
    </style>
    <meta http-equiv="refresh" content="30">
  </head>
  <body>
  
  <?php $program->tisk(); ?>
  
  <?php /* profilInfo(); */ ?>
  
  </body>
</html>
