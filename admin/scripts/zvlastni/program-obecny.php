<?php

$program = new Program();

?>
<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="refresh" content="30">
  <link rel="stylesheet" href="<?=$program->cssUrl()?>">
  <style>
    body {
      font-family: tahoma, sans;
      font-size: 11px;
      line-height: 1.2;
    }
    h2 {
      text-align: center;
    }
  </style>
</head>
<body>

  <?php $program->tisk(); ?>

</body>
</html>
