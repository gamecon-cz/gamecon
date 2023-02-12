<h3>Nový fake účet</h3>

<?php

use Gamecon\Role\Role;

/**
 * Stránka pro přidávání organizátorských entit / skupin
 *
 * nazev: Nová vypravěčská skupina
 * pravo: 102
 * submenu_group: 2
 * submenu_order: 1
 */

/** @var Uzivatel $u */

if(post('vypravec')) {
  $un = null;
  try {
    $un = Uzivatel::zPole(['login_uzivatele' => post('vypravec')], Uzivatel::FAKE);
  } catch(Exception $e) {
    echo '<p style="color:red">Zadaný login nelze vytvořit. Možná je už zabraný.</p>';
  }
  if($un) {
    $un->dejRoli(Role::VYPRAVECSKA_SKUPINA, $u);
    echo '<p style="color:green">Uživatel '.$un->jmenoNick().' ('.$un->id().') vytvořen a přidán do seznamu vypravěčů</p>';
  }
}

?>

<form method="post">
  Název: <input type="text" name="vypravec" />
  <input type="submit" value="Vytvořit" />
</form>

<p style="max-width:500px;font-size:120%">Pokud je potřeba vytvořit nový falešný a uvést ho jako vypravěče aktivity (např. Albi, Gamecon, Deskofobie, ...), vytvoří se formulářem výš. <b>Pozor:</b> Pokud místo konkrétních lidí uvedete jako vypravěče jenom tento fake účet, pochopitelně se jim nezapočtou slevy, nezablokuje slot v programu atd...</p>
