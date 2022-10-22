<?php

$program = new Program(null, [
    'technicke' => true,
    'skupiny'   => 'mistnosti',
    'prazdne'   => true,
]);

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
