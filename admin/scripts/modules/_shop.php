<?php

/**
 * DrD, Trojboj, Gamecon, Placení aj.
 *
 * nazev: Shop
 * pravo: 100
 */

function zabalJsSoubor($cestaNaWebu) {
  $verze = md5_file(WWW . '/' . $cestaNaWebu);
  $url = URL_WEBU . '/' . $cestaNaWebu . '?v=' . $verze;
  return $url;
}

?>

<link rel="stylesheet" href="soubory/ui/style.css">

<div id="preact-shop">Shop loading...</div>

<script>
    // Konstanty předáváné do Preactu (env.ts)
    window.GAMECON_KONSTANTY = {
        BASE_PATH_API: "<?= WWW . "/api/" ?>",
        ROK: <?= ROK ?>,
    }
</script>

<script type="module" src="<?= zabalJsSoubor('soubory/ui/bundle.js') ?>"></script>
