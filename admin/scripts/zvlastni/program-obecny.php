<?php

use Gamecon\Aktivita\Program;

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$program = new Program(systemoveNastaveni: $systemoveNastaveni);

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <title>Obecný program</title>
    <meta http-equiv="refresh" content="30">
    <?php foreach ($program->cssUrls() as $cssUrl) { ?>
        <link rel="stylesheet" href="<?= $cssUrl ?>">
    <?php } ?>
    <style>
        body {
            font-family: tahoma, sans, sans-serif;
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
