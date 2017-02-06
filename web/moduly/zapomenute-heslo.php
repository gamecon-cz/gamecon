<h1>Zapomenuté heslo</h1>
<?php

//////////////////////////
// warning, legacy shit //
// TODO přepsat         //
//////////////////////////

function nahodne_hex_cislo($pocet_znaku){
  $vysledek = "";
  for ($i = 0; $i < $pocet_znaku; $i++){
    $vysledek .= dechex(rand(0,15));
  }
  return $vysledek;
}

function mime_header_encode($text, $encoding = "utf-8") {
    return "=?$encoding?Q?" . imap_8bit($text) . "?=";
}

$chyba_zobraz='';
$mail_odeslan=0;

if (!empty($_POST["jak_najit"])){
  if ($_POST["jak_najit"] == "login"){
    $sql="select id_uzivatele,email1_uzivatele,login_uzivatele,pohlavi from uzivatele_hodnoty where login_uzivatele like '$_POST[login]'";
  }
  elseif ($_POST["jak_najit"] == "mail"){
    $sql="select id_uzivatele,email1_uzivatele,login_uzivatele,pohlavi from uzivatele_hodnoty where email1_uzivatele like '$_POST[mail]'";
  }
  $result=dbQuery($sql);
  if (mysqli_num_rows($result) <> 1){
    $chyba_zobraz .= "Chyba: Zadané uživatelské jméno nebo email neexistují.<br />";
  }
  else {
  
    $uzivatelPole = mysqli_fetch_assoc($result);
    $uzivatel = new Uzivatel($uzivatelPole);
    $id_uzivatele=$uzivatelPole['id_uzivatele'];
    $email_uzivatele=$uzivatelPole['email1_uzivatele'];
    $login_uzivatele=$uzivatelPole['login_uzivatele'];
    $pohlavi=$uzivatelPole['pohlavi'];
    $nove_heslo=nahodne_hex_cislo(10);
    $heslo_zasifrovane=md5($nove_heslo);
    $sql="update uzivatele_hodnoty set heslo_md5='$heslo_zasifrovane' where id_uzivatele=$id_uzivatele";
    dbQuery($sql);
      
    //poslání mailu
    $mail=new GcMail(hlaskaMail('zapomenuteHeslo',$uzivatel,$login_uzivatele,$nove_heslo));
    $mail->adresat($email_uzivatele);
    $mail->predmet('Znovuposlání hesla na Gamecon.cz');
    if($mail->odeslat())
    {    
      $chyba_zobraz="Email s novým heslem byl odeslán.";
      $mail_odeslan=1;
    }
    else
    {
      chyba('Chyba: Email s novým heslem NEBYL odeslán, uživatel má pravděpodobně nastavený neplatný email nebo nastala neočekávaná chyba databáze. Kontaktujte nás prosím e-mailem <a href="mailto:info@gamecon.cz">info@gamecon.cz</a>');
    }
    
    if($chyba_zobraz){
    oznameni($chyba_zobraz);
    echo "<div class=\"chyba_ramecek\">$chyba_zobraz</div>";
    unset($chyba_zobraz);
    }
      
  }
}
if($chyba_zobraz){
  chyba($chyba_zobraz);
  echo "<div class=\"chyba_ramecek\">$chyba_zobraz</div>";
  $chyba_zobraz='';
}
?>

<p>
Pokud jste zapomněli své heslo, můžete si nechat vygenerovat nové a zaslat si ho na email, který je pro váš účet aktivní. Po úspěšném přihlášení doporučujeme heslo změnit.
</p>


<?php
if ($mail_odeslan != 1)
{
  $emailPredvyplneny=get('mail')?get('mail'):'';
?>

<h2>Vygenerovat nové heslo a zaslat na email</h2>

<strong>Znám svůj login:</strong>
<form method="post">
  <input type="hidden" name="jak_najit" value="login">
  <input type="text" name="login">
  <input type="submit" value="odeslat">
</form>

<strong>Znám svůj e-mail:</strong>
<form method="post">
  <input type="hidden" name="jak_najit" value="mail">
  <input type="text" value="<?php echo $emailPredvyplneny ?>" name="mail">
  <input type="submit" value="odeslat">
</form>
<?php
}
?>
