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
  DBTest::resetujDB("_localhost-2023_06_21_21_58_55-dump.sql");
}

?>
<form method="POST">
  <input type="submit" name="db-reset" value="resetuj db" /></input>
</form>
<?php




exit(0);
