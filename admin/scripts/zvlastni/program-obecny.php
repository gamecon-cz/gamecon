<?php

use Gamecon\Aktivita\Program;

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <title>Obecný program</title>
    <meta http-equiv="refresh" content="30">
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

<?php Program::vypisPreact(true, "program-obecny"); ?>

</body>
</html>
