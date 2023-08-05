<?php

$url = Url::zAktualni();

if ($url->cast(0) != "test")
  return;

if (!TEST) {
?>
  <div style="background-color: red;width: fit-content;padding:18px;color:white;font-size: 4em;">
    TEST není povolen v nastavení
  </div>
<?php
  exit(1);
}

use Gamecon\Tests\DBTest;


if (isset($_POST["db-reset"])) {
  DBTest::resetujDB("gamecon-cista.sql.gz");
}

if (isset($_POST["db-cisti"])) {
  DBTest::vycistiDB();
}

if (isset($_POST["db-smaz"])) {
  DBTest::smazDbNakonec(false);
}

if (isset($_POST["admin-vytvor"])) {
  DBTest::vytvorAdmina();
}


?>

<fieldset>
  <legend>Hodnoty v cookies</legend>

  <label>Test: 
    <input type="text" id="test">
  </label>
  <br>
  
  <label>
    gamecon_test_db: 
    <span style="white-space: nowrap;">gamecon_test_<input type="text" id="gamecon_test_db"></span>
  </label>
  <br>
  
  <button onclick="uložZměny()">Ulož změny</button>
</fieldset>
<br>
  
<script>
  function getCookieValue(name) {
    var value = "; " + document.cookie;
    var parts = value.split("; " + name + "=");
    if (parts.length === 2) {
      return parts.pop().split(";").shift();
    }
    return null;
  }

  function setCookieValue(name, value) {
    document.cookie = name + "=" + value + "; path=/";
  }

  function uložZměny() {
    var testValue = document.getElementById("test").value;
    var testDBValue = document.getElementById("gamecon_test_db").value;

    setCookieValue("test", testValue);
    setCookieValue("gamecon_test_db", "gamecon_test_" + testDBValue);

    alert("Changes saved!");
  }

  var testValue = getCookieValue("test");
  var testDBValue = getCookieValue("gamecon_test_db");

  document.getElementById("test").value = testValue;
  document.getElementById("gamecon_test_db").value = testDBValue.replace("gamecon_test_", "");
</script>

<script>
  function potvrď(e, zprava) {
    if (!confirm(zprava)) {
      e.preventDefault();
    }
  }
</script>

<form method="POST">
  <input type="submit" name="db-reset" value="resetuj db" onclick="potvrď(event, 'doopravdy resetovat DB ?')" /></input>
  <input type="submit" name="db-cisti" value="vycisti db" onclick="potvrď(event, 'doopravdy vymazat data z DB ?')" /></input>
  <input type="submit" name="admin-vytvor" value="vytvoř admina" /></input>
  <input type="submit" name="db-smaz" value="Smaž všechny testovací db" onclick="potvrď(event, 'doopravdy smazat všechny testovací DB?')" /></input>
</form>
<?php




exit(0);
