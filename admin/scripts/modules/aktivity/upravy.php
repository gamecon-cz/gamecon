<?php

/**
 * Stránka pro tvorbu a editaci aktivit. Brand new.
 *
 * nazev: Nová aktivita
 * pravo: 102
 */

if(Aktivita::editorTestJson())        // samo sebe volání ajaxu
  die(Aktivita::editorChybyJson());

try {
  if($a = Aktivita::editorZpracuj())  // úspěšné uložení změn ve formuláři
    if($a->nova())
      back('aktivity/upravy?aktivitaId='.$a->id());
    else
      back();
} catch(ObrazekException $e) {
  if(get('aktivitaId'))
    chyba('Obrázek nelze přečíst.');
  else {
    oznameni('Aktivita vytvořena.', false); // hack - obrázek selhal, ale zbytek nejspíš prošel, vypíšeme úspěch
    back('aktivity');
  }
}

$a=Aktivita::zId(get('aktivitaId'));  // načtení aktivity podle předaného ID
$editor=Aktivita::editor($a);         // načtení html editoru aktivity

?>



<form method="post" enctype="multipart/form-data" style="position: relative">
  <?=$editor?>
</form>
