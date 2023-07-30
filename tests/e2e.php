<?php

$url = Url::zAktualni();

if ($url->cast(0) != "e2e")
  return;

use Gamecon\Tests\DBTest;

if (!E2E_TESTING) {
?>
  <div style="background-color: red;width: fit-content;padding:18px;color:white;font-size: 4em;">
    E2E_TESTING není povolen v nastavení
  </div>
<?php
  exit(1);
}

if (isset($_POST["db-reset"])) {
  // TODO: vytáhnout někam kde to dává smysl
  if (!defined('DB_TEST_PREFIX')) define('DB_TEST_PREFIX', 'gamecon_test_');
  DBTest::resetujDB("gamecon-cista.sql.gz");
}

if (isset($_POST["db-cisti"])) {
  DBTest::vycistiDB();
}

if (isset($_POST["admin-vytvor"])) {
  DBTest::vytvorAdmina();
}


?>
<script>
function potvrd(e, zprava)
{
  if(!confirm(zprava)) {
    e.preventDefault();
  }
}
</script>

<form method="POST">
  <input type="submit" name="db-reset" value="resetuj db" onclick="potvrd(event, 'doopravdy resetovat DB ?')" /></input>
  <input type="submit" name="db-cisti" value="vycisti db" onclick="potvrd(event, 'doopravdy vymazat data z DB ?')" /></input>
  <input type="submit" name="admin-vytvor" value="vytvoř admina" /></input>
</form>
<?php




exit(0);
