<?php

/**
 * DrD, Trojboj, Gamecon, Placení aj.
 *
 * nazev: Shop
 * pravo: 100
 */

/**
 * @var Uzivatel|null $uPracovni
 */

function zabalAdminSoubor(string $cestaKSouboru): string {
    return $cestaKSouboru . '?version=' . md5_file(ADMIN . '/' . $cestaKSouboru);
}

if (!empty($_POST['prodej-mrizka'])) {
    $prodeje = $_POST['prodej-mrizka'];

    foreach ($prodeje as $prodej) {
        $prodej['id_uzivatele'] = $uPracovni ? $uPracovni->id() : Uzivatel::SYSTEM;

        for ($kusu = $prodej['kusu'] ?? 1, $i = 1; $i <= $kusu; $i++) {
            dbQuery('INSERT INTO shop_nakupy(id_uzivatele,id_predmetu,rok,cena_nakupni,datum)
    VALUES (' . $prodej['id_uzivatele'] . ',' . $prodej['id_predmetu'] . ',' . ROCNIK . ',(SELECT cena_aktualni FROM shop_predmety WHERE id_predmetu=' . $prodej['id_predmetu'] . '),NOW())');
        }
        $idPredmetu = (int)$prodej['id_predmetu'];
        $nazevPredmetu = dbOneCol(
            <<<SQL
      SELECT nazev FROM shop_predmety
      WHERE id_predmetu = $idPredmetu
      SQL
        );
    }

    oznameni("350 poprvé ... podruhé ... Prodáno !!");
    back();
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
