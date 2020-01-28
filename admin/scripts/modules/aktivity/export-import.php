<?php
namespace Gamecon\Admin\Modules\Aktivity;

use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleApiTokenStorage;

/**
 * Stránka pro hromadný export a opětovný import aktivit.
 *
 * nazev: Export & import
 * pravo: 102
 */

$g = new GoogleApiTokenStorage();
var_dump($g->deleteTokenFor(123));
var_dump($g->hasTokenFor(123));
var_dump($g->setTokenFor(['foo', 'aha' => 'coze'], 123));
var_dump($g->hasTokenFor(123));
var_dump($g->getTokenFor(123));
var_dump($g->deleteTokenFor(123));
die;

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
