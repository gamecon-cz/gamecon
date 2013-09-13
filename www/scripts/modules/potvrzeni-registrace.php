<?

$chyba_zobraz='';

if ($url->cast(1) == "email_znovu")
{
  Throw new Exception('Neimplementováno. Kontaktujte administrátora.');
  $sql="select email1_uzivatele,random from uzivatele_hodnoty where id_uzivatele='".$url->cast(2)."'";
  $result=dbQuery($db_jmeno , $sql, $db_spojeni);
  $email_znovuposlani=mysql_result($result,0,0);
  $random_znovuposlani=mysql_result($result,0,1);
  //poslání mailu
    $to ="$email_znovuposlani";
    $subject="Registrace na Gamecon.cz";
    $message="Dobrý den,<br /><br />
    \nzaregistroval jsi se na serveru <a href=\"http://www.gamecon.cz\">Gamecon.cz</a>. Pro potvrzení tvé e-mailové prosím následuj níže uvedený odkaz:<br /><br />
    <a href=\"http://www.gamecon.cz/potvrzeni_registrace/$random_znovuposlani\">www.gamecon.cz/potvrzeni_registrace/$random_znovuposlani</a>";
    $message_2=iconv("utf-8","iso-8859-2","$message");
    $headers="Reply-to: mailman@gamecon.cz\nContent-Type:text/html; charset=iso-8859-2\nFrom:=?iso-8859-2?B?". base64_encode("GameCon MailMan")."?=<info@gamecon.cz>\n";
    mail($to, mime_header_encode(iconv("utf-8","iso-8859-2","$subject"),"iso-8859-2"), $message_2, $headers);
    $chyba_zobraz.="Potvrzovací email byl znovuodeslán.";
}
elseif (strlen($url->cast(1)) != 20){
  $chyba_zobraz.="Chyba: Neplatný formát potvrzovacího čísla";
}
else {
  $sql="select id_uzivatele,funkce_uzivatele from uzivatele_hodnoty where random LIKE '".$url->cast(1)."'";
  $result=dbQuery($db_jmeno , $sql, $db_spojeni);
  if (mysql_num_rows($result) == 1){
    $id_registrovaneho=mysql_result($result,0,0);
    $funkce_registrovaneho=mysql_result($result,0,1);
    if ($funkce_registrovaneho == 0)
    {
      $sql="UPDATE uzivatele_hodnoty SET funkce_uzivatele='1' WHERE id_uzivatele='$id_registrovaneho'";
      $result=dbQuery($db_jmeno , $sql, $db_spojeni);
      //$chyba_zobraz.=hlaska('aktivaceOk');
      $u=Uzivatel::prihlasId($id_registrovaneho);
      //oznameni(hlaska('aktivaceOk'),false); //nepřesměrovávat, provedeme ručně dál
      //back('/registrace');
      $chyba_zobraz.='Účet byl aktvován. Děkujeme. Nyní se můžete přihlásit a upravit své údaje: <a href="/registrace">přihlásit</a>';
    }
    else {
      $chyba_zobraz.="Chyba: Tento uživatel má již účet aktivován.";
    }
  }
  else {
    $chyba_zobraz.="Chyba: Uživatel se zadaným aktivačním kódem neexistuje.";
  }
}

if($chyba_zobraz)
{
  echo "<div class=\"chyba_ramecek\">$chyba_zobraz</div>";
  unset($chyba_zobraz);
}

?>
