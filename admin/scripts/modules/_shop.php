<?php

/**
 * DrD, Trojboj, Gamecon, Placení aj.
 *
 * nazev: Shop
 * pravo: 100
 */

/**
 * @var Uzivatel|null $uPracovni
 * @var Uzivatel $u
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

function zabalAdminSoubor(string $cestaKSouboru): string
{
    return $cestaKSouboru . '?version=' . md5_file(ADMIN . '/' . $cestaKSouboru);
}

?>

<link rel="stylesheet" href="<?= zabalAdminSoubor('files/ui/style.css') ?>">

<div id="preact-obchod">Obchod se načítá ...</div>

<?php require_once __DIR__ . '/_jwt-konstanty.php'; ?>
<script>
    window.GAMECON_KONSTANTY = {
        BASE_PATH_API: "<?= URL_ADMIN . "/api/" ?>",
        ROCNIK: <?= ROCNIK ?>,
        <?= jwtKonstantyJs($u, $systemoveNastaveni) ?>
    }
</script>

<script type="module" src="<?= zabalAdminSoubor('files/ui/bundle.js') ?>"></script>
