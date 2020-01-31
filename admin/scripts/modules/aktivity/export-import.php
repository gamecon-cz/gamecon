<?php
namespace Gamecon\Admin\Modules\Aktivity;

use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleApiClient;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleApiCredentials;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleApiTokenStorage;

/**
 * Stránka pro hromadný export a opětovný import aktivit.
 *
 * nazev: Export & import
 * pravo: 102
 */

/** @type \Uzivatel $u */
$googleApiClient = new GoogleApiClient(
  new GoogleApiCredentials(GOOGLE_API_CREDENTIALS),
  new GoogleApiTokenStorage(),
  $u->id()
);

if (isset($_GET['code'])) {
  $googleApiClient->validateAuthorizationByCode($_GET['code']);
}

var_dump($googleApiClient);
var_dump($googleApiClient->isAuthorized());
if (!$googleApiClient->isAuthorized()) {
  echo $googleApiClient->getAuthorizationUrl();
}
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
