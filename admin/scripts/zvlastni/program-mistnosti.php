<?php

use Gamecon\Aktivita\Program;

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

// todo(tym):
/*
    nastaveni: [
        Program::INTERNI => true,
        Program::SKUPINY => Program::SKUPINY_MISTNOSTI,
        Program::PRAZDNE => true,
    ],
*/
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <title>Program</title>
    <style>
        body {
            font-family: tahoma, sans-serif;
            font-size: 11px;
            line-height: 1.2;
        }
    </style>
</head>
<body>

<?php Program::vypisPreact($uPracovni, true, "program-mistnosti"); ?>

</body>
</html>
