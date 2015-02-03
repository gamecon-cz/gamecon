<h3>Nová organizátorská skupina</h3>

<?php

/** 
 * Stránka pro přidávání organizátorských entit / skupin
 *
 * nazev: Nová org skupina
 * pravo: 102
 */

if(post('vypravec'))
{
  $a=dbOneLineS('SELECT * FROM uzivatele_hodnoty WHERE login_uzivatele=$0',array(post('vypravec')));
  if($a)
    echo '<p style="color:red">Zadaný login už existuje</p>';
  else
  {
    dbQueryS('INSERT INTO uzivatele_hodnoty(login_uzivatele) VALUES ($0)',array(post('vypravec')));
    $noveId=mysql_insert_id();
    dbQueryS('INSERT INTO r_uzivatele_zidle(id_uzivatele,id_zidle) VALUES ($0,$1)',array($noveId,Z_ORG_SKUPINA));
    echo '<p style="color:green">Uživatel '.post('vypravec').' ('.$noveId.') vytvořen</p>';
  }
}

?>

<form method="post">
  Název: <input type="text" name="vypravec" />
  <input type="submit" value="Vytvořit" />
</form>

<p>Toto slouží k přidání nové entity mezi organizátory aktivit (vypravěče). Typicky firmy na deskovky, tvůrčí skupiny larpů, … Vytvoří se tím uživatel s odpovídajícím loginem a objeví se v nabídce dostupných organizátorů. Nejde použít už zabrané uživatelské loginy. Pokud chcete přidat už existujícího uživatele mezi vypravěče, vyberte ho pro práci na Úvodu a pak mu dejte židli vypravěč přes <a href="prava">Práva</a>.</p>
