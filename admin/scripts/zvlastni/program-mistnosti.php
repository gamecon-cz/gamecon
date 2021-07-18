<?php

$program = new Program(null, [
    'technicke' => true,
    'skupiny' => 'mistnosti',
    'prazdne' => true,
]);

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <title>Program</title>
    <link rel="stylesheet" href="<?= $program->cssUrl() ?>">
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
