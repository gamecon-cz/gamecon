<?php

/**
 * nazev: Ubytování
 * pravo: 110 Administrace - panel Nastavení
 */
function zabalAdminSoubor(string $cestaKSouboru): string
{
    return $cestaKSouboru . '?version=' . md5_file(ADMIN . '/' . $cestaKSouboru);
}

?>

<link rel="stylesheet" href="<?= zabalAdminSoubor('files/ui/style.css') ?>">

<div id="preact-ubytovani-nastaveni">Obchod se načítá ...</div>

<script>
    // Konstanty předáváné do Preactu (env.ts)
    window.GAMECON_KONSTANTY = {
        BASE_PATH_API: "<?= URL_ADMIN . "/api/" ?>",
        ROCNIK: <?= ROCNIK ?>,
    }
</script>

<script type="module" src="<?= zabalAdminSoubor('files/ui/bundle.js') ?>"></script>


