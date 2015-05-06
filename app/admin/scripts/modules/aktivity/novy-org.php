<h3>Nový fake účet</h3>

<?php

/**
 * Stránka pro přidávání organizátorských entit / skupin
 *
 * nazev: Nový fake účet
 * pravo: 102
 */

if(post('vypravec')) {
  $un = null;
  try {
    $un = Uzivatel::zPole(['login_uzivatele' => post('vypravec')], Uzivatel::FAKE);
  } catch(Exception $e) {
    echo '<p style="color:red">Zadaný login nelze vytvořit. Možná je už zabraný.</p>';
  }
  if($un) {
    $un->dejZidli(Z_ORG_SKUPINA);
    echo '<p style="color:green">Uživatel '.$un->jmenoNick().' ('.$un->id().') vytvořen a přidán do seznamu vypravěčů</p>';
  }
}

?>

<form method="post">
  Název: <input type="text" name="vypravec" />
  <input type="submit" value="Vytvořit" />
</form>

<p style="max-width:500px;font-size:120%">Pokud je potřeba vytvořit nový falešný a uvést ho jako vypravěče aktivity (např. Albi, Gamecon, Deskofobie, ...), vytvoří se formulářem výš. <b>Pozor:</b> Pokud místo konkrétních lidí uvedete jako vypravěče jenom tento fake účet, pochopitelně se jim nezapočtou slevy, nezablokuje slot v programu atd...</p>
