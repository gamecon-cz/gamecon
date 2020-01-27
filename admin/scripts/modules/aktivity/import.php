<?php

/**
 * Stránka pro hromadný export a opětovný import aktivit.
 *
 * nazev: Export & import
 * pravo: 102
 */

try {
  if ($a = Aktivita::editorZpracuj())  // úspěšné uložení změn ve formuláři
    if ($a->nova())
      back('aktivity/upravy?aktivitaId=' . $a->id());
    else
      back();
} catch (ObrazekException $e) {
  if (get('aktivitaId'))
    chyba('Obrázek nelze přečíst.');
  else {
    oznameni('Aktivita vytvořena.', false); // hack - obrázek selhal, ale zbytek nejspíš prošel, vypíšeme úspěch
    back('aktivity');
  }
}

$a = Aktivita::zId(get('aktivitaId'));  // načtení aktivity podle předaného ID
$editorAktivity = Aktivita::editor($a); // načtení html editoru aktivity
?>

<a href="?export"
<form method="post" enctype="multipart/form-data" style="position: relative">
  <?= $editorAktivity ?>
</form>
