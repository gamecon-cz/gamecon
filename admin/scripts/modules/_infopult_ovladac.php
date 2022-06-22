<?php

/**
 * akce proveditelné z infopult záložky
 */

use \Gamecon\Cas\DateTimeCz;
use \Gamecon\Cas\DateTimeGamecon;

/**
 * @var Uzivatel|null|void $u
 * @var Uzivatel|null|void $uPracovni
 * @var \Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac
 */

/**
 * @var \Shop|null $shop
 */

if (!empty($_POST['datMaterialy']) && $uPracovni && $uPracovni->gcPrihlasen()) {
  $uPracovni->dejZidli(ZIDLE_PRITOMEN, $u->id());
  back();
}

if (post('platba') && $uPracovni) {
  if (!$uPracovni->gcPrihlasen()) {
      varovani('Platba připsána uživateli, který není přihlášen na Gamecon', false);
  }
  try {
      $uPracovni->finance()->pripis(post('platba'), $u, post('poznamka'), post('idPohybu'));
  } catch (DbDuplicateEntryException $dbDuplicateEntryException) {
      if (post('idPohybu') && FioPlatba::existujePodleFioId(post('idPohybu'))) {
          chyba(sprintf('Tato platba s Fio ID %d již existuje', post('idPohybu')), false);
      } else {
          chyba(
              sprintf("Platbu se nepodařilo uložit. Duplicitní záznam: '%s'", $dbDuplicateEntryException->getMessage()),
              false
          );
      }
  }
  back();
}

if (!empty($_POST['gcPrihlas']) && $uPracovni && !$uPracovni->gcPrihlasen()) {
  $uPracovni->gcPrihlas();
  back();
}

if (!empty($_POST['rychloreg'])) {
  $tab = $_POST['rychloreg'];
  if (empty($tab['login_uzivatele'])) {
      $tab['login_uzivatele'] = $tab['email1_uzivatele'];
  }
  $tab['nechce_maily'] = isset($tab['nechce_maily']) ? dbNow() : null;
  try {
      $nid = Uzivatel::rychloreg($tab, [
          'informovat' => post('informovat'),
      ]);
  } catch (DuplicitniEmailException $e) {
      throw new Chyba('Uživatel s zadaným e-mailem už v databázi existuje');
  } catch (DuplicitniLoginException $e) {
      throw new Chyba('Uživatel s loginem odpovídajícím zadanému e-mailu už v databázi existuje');
  }
  if ($nid) {
      if ($uPracovni) {
          Uzivatel::odhlasKlic('uzivatel_pracovni');
      }
      $_SESSION["id_uzivatele"] = $nid;
      $uPracovni = Uzivatel::prihlasId($nid, 'uzivatel_pracovni');
      if (!empty($_POST['vcetnePrihlaseni'])) {
          $uPracovni->gcPrihlas();
      }
      back();
  }
}

if (!empty($_POST['telefon']) && $uPracovni) {
  dbQueryS('UPDATE uzivatele_hodnoty SET telefon_uzivatele=$0 WHERE id_uzivatele=' . $uPracovni->id(), [$_POST['telefon']]);
  $uPracovni->otoc();
  back();
}

if (!empty($_POST['prodej'])) {
  $prodej = $_POST['prodej'];
  unset($prodej['odeslano']);
  $prodej['id_uzivatele'] = $uPracovni ? $uPracovni->id() : 0;
  for ($kusu = $prodej['kusu'] ?? 1, $i = 1; $i <= $kusu; $i++) {
      dbQuery('INSERT INTO shop_nakupy(id_uzivatele,id_predmetu,rok,cena_nakupni,datum)
  VALUES (' . $prodej['id_uzivatele'] . ',' . $prodej['id_predmetu'] . ',' . ROK . ',(SELECT cena_aktualni FROM shop_predmety WHERE id_predmetu=' . $prodej['id_predmetu'] . '),NOW())');
  }
  $idPredmetu = (int)$prodej['id_predmetu'];
  $nazevPredmetu = dbOneCol(
      <<<SQL
      SELECT nazev FROM shop_predmety
      WHERE id_predmetu = $idPredmetu
      SQL
  );
  $yu = '';
  if ($kusu >= 5) {
      $yu = 'ů';
  } elseif ($kusu > 1) {
      $yu = 'y';
  }
  oznameni("Prodáno $kusu kus$yu $nazevPredmetu");
  back();
}

if (!empty($_POST['gcOdhlas']) && $uPracovni && !$uPracovni->gcPritomen()) {
  $uPracovni->gcOdhlas();
  back();
}

if (post('gcOdjed')) {
  $uPracovni->gcOdjed();
  back();
}

// TODO: mělo by být obsaženo v modelové třídě
/**
* @param mixin $udaje 
* @param int $uPracovniId
* @param \Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac
*/
function updateUzivatelHodnoty($udaje, $uPracovniId, $vyjimkovac)
{
  try {
      dbUpdate('uzivatele_hodnoty', $udaje, ['id_uzivatele' => $uPracovniId]);
  } catch (Exception $e) {
      $vyjimkovac->zaloguj($e);
      chyba('Došlo k neočekávané chybě.');
  }
}

/* Editace v kartě Pŕehled */
if ($uPracovni && post('prehledUprava')) {
  $udaje = post('udaje');

  foreach ([
      'potvrzeni_zakonneho_zastupce',
      'potvrzeni_proti_covid19_overeno_kdy',
  ] as &$pole) {
      if (isset($udaje[$pole])) {
          // pokud je hodnota "" tak to znamená že nedošlo ke změně
          if ($udaje[$pole] == "")
              unset($udaje[$pole]);
          else
              $udaje[$pole] = date('Y-m-d');
      } else {
          $udaje[$pole] = null;
      }
  }

  // TODO(SECURITY): nebezpečné krmit data do databáze tímhle způsobem Každý si vytvořit do html formuláře input který se pak také propíŠe do DB
  updateUzivatelHodnoty($udaje, $uPracovni->id(), $vyjimkovac);
  back();
}

if (post('zpracujUbytovani')) {
  $shop->zpracujUbytovani();
  oznameni('Ubytování uloženo');
}

if (post('pridelitPokoj') && $uPracovni) {
  $pokojPost = post('pokoj');
  Pokoj::ubytujNaCislo($uPracovni, $pokojPost);
  oznameni('Pokoj přidělen', false);
  if ($_SERVER['HTTP_REFERER']) {
      parse_str($_SERVER['QUERY_STRING'], $query_string);
      $query_string['pokoj'] = $pokojPost;
      unset($query_string['req']);
      $query_string = http_build_query($query_string);
      $targetAddress = explode("?", $_SERVER['HTTP_REFERER'])[0];
      header('Location: ' . $targetAddress . "?" . $query_string, true, 303);
  } else
      back();
}


if (post('zmenitUdaj') && $uPracovni) {
  $udaje = post('udaj');
  if ($udaje['op'] ?? null) {
      $uPracovni->cisloOp($udaje['op']);
      unset($udaje['op']);
  }
  if (empty($udaje['potvrzeni_zakonneho_zastupce'])) {
      // datum potvrzeni je odskrnute (prohlizec nezaskrtly chceckbox neposle), musime ho smazat
      $udaje['potvrzeni_zakonneho_zastupce'] = null;
  }
  if (empty($udaje['potvrzeni_proti_covid19_overeno_kdy'])) {
      // datum potvrzeni je odskrnute (prohlizec nezaskrtly chceckbox neposle), musime ho smazat
      $udaje['potvrzeni_proti_covid19_overeno_kdy'] = null;
  }
  try {
      dbUpdate('uzivatele_hodnoty', $udaje, ['id_uzivatele' => $uPracovni->id()]);
  } catch (DbDuplicateEntryException $e) {
      if ($e->key() === 'email1_uzivatele') {
          chyba('Uživatel se stejným e-mailem již existuje.');
      } else if ($e->key() === 'login_uzivatele') {
          chyba('Uživatel se stejným e-mailem již existuje.');
      } else {
          chyba('Uživatel se stejným údajem již existuje.');
      }
  } catch (Exception $e) {
      $vyjimkovac->zaloguj($e);
      chyba('Došlo k neočekávané chybě.');
  }

  $uPracovni->otoc();
  back();
}
