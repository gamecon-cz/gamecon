<?php

use Gamecon\Shop\Shop;

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

if (!empty($_POST['prodej-mrizka'])) {
    $prodeje             = $_POST['prodej-mrizka'];
    $rocnik              = $systemoveNastaveni->rocnik();
    $prodejIdUzivatele   = $uPracovni ? $uPracovni->id() : Uzivatel::SYSTEM;
    $prodejIdObjednatele = $u->id();
    $shop                = new Shop(
        zakaznik: $uPracovni ?? Uzivatel::zId(Uzivatel::SYSTEM),
        objednatel: $u,
        systemoveNastaveni: $systemoveNastaveni
    );
    foreach ($prodeje as $prodej) {
        $prodejIdPredmetu = (int)$prodej['id_predmetu'];
        $kusu             = (int)($prodej['kusu'] ?? 1);
        $shop->prodat($prodejIdPredmetu, $kusu, false);
    }
    $pocetTypuPredmetu = count($prodej);
    oznameni("Předměty prodány.");
}

?>

<!-- Náhrada za api call -->
<form id="prodej-mrizka-form" method="POST" style="display:none;"></form>

<link rel="stylesheet" href="<?= zabalAdminSoubor('files/ui/style.css') ?>">

<div id="preact-obchod">Obchod se načítá ...</div>

<script>
    // Konstanty předáváné do Preactu (env.ts)
    window.GAMECON_KONSTANTY = {
        BASE_PATH_API: "<?= URL_ADMIN . "/api/" ?>",
        ROCNIK: <?= ROCNIK ?>,
    }
</script>

<script type="module" src="<?= zabalAdminSoubor('files/ui/bundle.js') ?>"></script>
