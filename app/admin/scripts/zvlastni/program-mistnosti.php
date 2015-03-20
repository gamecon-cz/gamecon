<?php

$p = new Program(null, [
  'technicke' =>  'true',
  'skupiny'   =>  'mistnosti',
]);

?>
<!DOCTYPE html>
<html>
  <head>
    <style>
      body { font-family: sans-serif; font-size: 12px; }
    </style>
    <?php $p->css(); ?>
  </head>
  <body>
    <?php $p->tisk(); ?>
  </body>
</html>
