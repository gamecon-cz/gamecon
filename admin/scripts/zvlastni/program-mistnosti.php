<?php

use Gamecon\Aktivita\Program;

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$program = new Program(
    systemoveNastaveni: $systemoveNastaveni,
    nastaveni: [
        Program::INTERNI => true,
        Program::SKUPINY => Program::SKUPINY_MISTNOSTI,
        Program::PRAZDNE => true,
    ],
);

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <title>Program</title>
    <?php foreach ($program->cssUrls() as $cssUrl) { ?>
        <link rel="stylesheet" href="<?= $cssUrl ?>">
    <?php } ?>
    <style>
        body {
            font-family: tahoma, sans-serif;
            font-size: 11px;
            line-height: 1.2;
        }
    </style>
</head>
<body>

<?php $program->tisk(); ?>

</body>
</html>
