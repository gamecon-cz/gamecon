<?php

//samo sebe volání ajaxu
if(isset($_GET['testMailu']))
{
  if(mysql_num_rows(dbQueryS('SELECT 1 FROM uzivatele_hodnoty WHERE email1_uzivatele=$0',array($_GET['mail'])))>0)
    echo('{"vysledek":true}');
  else
    echo('{"vysledek":false}');
  exit;
}

//zpracování úpravy dat
if($u && isset($_POST['upravit']))
{
  $tab=$_POST['tab'];
  $tab['id_uzivatele']=$u->id();
  $narozeni=new DateTime(strtr(post('datumNarozeni'),'.','-'));
  $tab['datum_narozeni']=$narozeni->format('Y-m-d');
  if(post('heslo2'))
    $tab['heslo_md5']=md5(post('heslo2'));
  dbInsertUpdate('uzivatele_hodnoty',$tab);
  $u->avatarNactiPost('obrazek');
  $u->otoc();
  oznameni(hlaska('upravaUzivatele'));
}

//přihlášení k stránkám, bez registrace
if( !$u && ( post('prihlasit') || post('prihlasit2') ) )
{
  if($u=Uzivatel::prihlas($_POST['tab']['email1_uzivatele'],$_POST['heslo']))
  {
    if(post('prihlasit2')) //příhlásit i na GC
    {
      if($u->gcPrihlasen())
        oznameni(hlaska('uzPrihlasen'),false); //nepřesměrovávat, provedeme ručně dál
      else
        oznameni(hlaska('nyniPrihlaska'),false);
      back('prihlaska');
    }
    else
      back();
  }
  else
    chyba(hlaska('chybaPrihlaseni'));
}

//registrace nového uživatele
if(!$u && (post('registrovat')||post('registrovatAPrihlasit')))
{
  $tab=$_POST['tab'];
  $narozeni=new DateTime(strtr(post('datumNarozeni'),'.','-'));
  $tab['datum_narozeni']=$narozeni->format('Y-m-d');
  $tab['heslo_md5']=md5(post('heslo2'));
  $u=Uzivatel::prihlasId(Uzivatel::registruj($tab));
  $u->avatarNactiPost('obrazek');
  if(post('registrovatAPrihlasit'))
  {
    oznameni(hlaska('regOkNyniPrihlaska'),false); //nepřesměrovávat, provedeme ručně dál
    back('prihlaska');
  }
  else
    oznameni(hlaska('regOk'));
}

////////////////////////////////////////

$pokracovat=isset($_GET['prihlaska']); //pokračovat v přihlášce dle GETu?
if($u && $pokracovat)
  exit(header('Location: /prihlaska'));

//todo gamecon neběží a podobně

$udb=array();
if($u)
  $udb=$u->rawDb();
$avatar=$u?$u->avatar():Uzivatel::avatarDefault();
// zabrané přezdívky uživatelů
$o=dbQuery('SELECT login_uzivatele FROM uzivatele_hodnoty '.($u?'WHERE id_uzivatele!='.$u->id():''));
$loginy='';
while($r=mysql_fetch_row($o))
  $loginy.='"'.strtolower($r[0]).'",';
$loginy=substr($loginy,0,-1);

?>



<script>
$(function(){
  function formChyby(){
    err='';
    if( [<?=$loginy?>].indexOf( $('[name="tab[login_uzivatele]"]').val().toLowerCase() )!=-1 )
    {
      err+='Přezdívka je už zabraná. ';
      <?php if(!$u){ ?>
      err+='Jestli je tvoje, zkus se přihlásit nebo kliknout na „zapomenuté heslo“ vpravo nahoře.\n';
      <?php } ?>
      return err;
    }
    if(!$('[name="tab[jmeno_uzivatele]"]').val()) err+='Je třeba vyplnit jméno.\n';
    if(!$('[name="tab[prijmeni_uzivatele]"]').val()) err+='Je třeba vyplnit příjmení.\n';
    if(!$('[name="tab[pohlavi]"]').val()) err+='Je třeba vybrat pohlaví.\n';
    if($('[name="tab[ulice_a_cp_uzivatele]"]').val().search(/.+ [\d/a-z]+$/)==-1) err+='Vyplňte prosím ulici, např. „Česká 27“.\n';
    if(!$('[name="tab[mesto_uzivatele]"]').val()) err+='Vyplňte prosím město.\n';
    if($('[name="tab[psc_uzivatele]"]').val().search(/^[\d ]+$/)==-1) err+='Vyplňte prosím PSČ, např. 602 00.\n';
    if($('[name="tab[telefon_uzivatele]"]').val().search(/^[\d \+]+$/)==-1) err+='Vyplňte prosím telefon, např. +420 123 456 789.\n';
    if($('[name=datumNarozeni]').val().search(/^\d{1,2}\.\d{1,2}\.\d{4}$/)==-1) err+='Datum narození, např. 1.1.1990.\n';
    <?php if(!$u){ ?>
    if( !$('[name=heslo2]').val() || !$('[name=heslo3]').val() ) err+='Je třeba vyplnit heslo.\n';
    <?php } ?>
    if( ($('[name=heslo2]').val()) != ($('[name=heslo3]').val()) ) err+='Hesla se neshodují.\n';
    if(!jeMail($('[name="tab[email1_uzivatele]"]').val())) err+='Je třeba zadat platný e-mail.\n';
    if(!$('[name="tab[pohlavi]"]:checked').size()) err+='Není vybráno pohlaví.\n';
    return err;
  }
  function jeMail(mail){
    return mail.search(/^[a-z0-9_\-\.]+@[a-z0-9_\-\.]+\.(cz|com|sk|net|eu|org|uk|tk)$/)==0; //todo
  }
  function registrovanyMail(mail,complete){
    $.getJSON('',{testMailu:true,mail:mail},function(data){
      complete(data.vysledek);
    });
  }
  <?php if(!$u){ ?>
  $('[name="tab[email1_uzivatele]"]').keyup(function(){
    mail=$('[name="tab[email1_uzivatele]"]').val().toLowerCase();
    $('[name="tab[email1_uzivatele]"]').val(mail);
    if(jeMail(mail)){
      registrovanyMail(mail,function(jeRegistrovany){
        if(jeRegistrovany){
          $('#existujiciUzivatel').slideDown('slow');
          $('#neexistujiciUzivatel').slideUp('slow');
        }else{
          $('#existujiciUzivatel').slideUp('slow');
          $('#neexistujiciUzivatel').slideDown('slow');
        }
      });
    }else if(mail==''){
      $('#existujiciUzivatel').slideUp('slow');
      $('#neexistujiciUzivatel').slideUp('slow');
    }
  });
  <?php } ?>
  $('[name=upravit], [name=registrovat], [name=registrovatAPrihlasit]').click(function(){
    if(!formChyby())
      return true;
    alert(formChyby());
    return false;  
  });
  $('[name=prihlasit], [name=prihlasit2]').click(function(){
    if(jeMail($('[name="tab[email1_uzivatele]"]').val()))
      return true;
    alert('Je třeba zadat platný e-mail.');
    return false;  
  });
});
</script>



<div class="registrace">

<?php if(!$u && !$pokracovat){ ?>
<p>Pomocí tohoto formuláře se můžeš zaregistrovat na stránky a volitelně i přihlásit na GameCon.</p>
<?php } ?>
<?php if(!$u && $pokracovat){ ?>
<p>Pomocí tohoto formuláře se můžeš přihlásit nebo zaregistrovat na stránky a přihlásit na GameCon. Podle e-mailu se ti zobrazí další položky.</p>
<?php } ?>
<?php if($u && !$pokracovat){ ?>
<p>Zde můžeš upravit svoje registrační údaje. Pokud nechceš měnit heslo, nech obě políčka prázdná.</p>
<?php } ?>

<form method="post" id="regForm" enctype="multipart/form-data">
  <input type="text" placeholder="e-mail" name="tab[email1_uzivatele]" value="<?=@$udb['email1_uzivatele']?>">
  <div id="existujiciUzivatel" style="display:none">
    <div class="pokyn">Uživatel s tímto e-mailem už existuje. Pokud jsi to ty, přihlaš se svým heslem nebo si nech <a href="/zapomenute-heslo" tabindex="10">vygenerovat nové</a>, pokud si ho nepamatuješ.</div>
    <input type="password" placeholder="heslo" name="heslo"><br>
    <?php if(!$pokracovat){ ?>
    <input type="submit" name="prihlasit" value="Přihlásit">
    <?php } ?>
    <?php if(REGISTRACE_AKTIVNI){ ?>
    <input type="submit" name="prihlasit2" value="Přihlásit na GameCon">
    <?php } ?>
  </div>
  <div id="neexistujiciUzivatel" <?php if(!$u){ ?>style="display:none"<?php } ?>>
    <?php if(!$u){ ?>
    <div class="pokyn">Podle e-mailu se registruješ na GameCon poprve, vyplň prosím registrační údaje. Informace o GameConu ti budeme dávat vědět e-mailem.</div>
    <?php } ?>
    <br>
    <input type="text" placeholder="Přezdívka" name="tab[login_uzivatele]" value="<?=@$udb['login_uzivatele']?>">
    <input type="text" placeholder="Jméno" name="tab[jmeno_uzivatele]" value="<?=@$udb['jmeno_uzivatele']?>">
    <input type="text" placeholder="Příjmení" name="tab[prijmeni_uzivatele]" value="<?=@$udb['prijmeni_uzivatele']?>">
    <input type="radio" name="tab[pohlavi]" value="f" id="pohlaviZena"
      <?php if(@$udb['pohlavi']=='f'){ ?>checked<?php } ?>>
      <label for="pohlaviZena">Žena</label> &ensp;
    <input type="radio" name="tab[pohlavi]" value="m" id="pohlaviMuz"
      <?php if(@$udb['pohlavi']=='m'){ ?>checked<?php } ?>>
      <label for="pohlaviMuz">Muž</label>
    <input type="text" placeholder="Ulice a číslo popisné" name="tab[ulice_a_cp_uzivatele]" value="<?=@$udb['ulice_a_cp_uzivatele']?>">
    <input type="text" placeholder="Město" name="tab[mesto_uzivatele]" value="<?=@$udb['mesto_uzivatele']?>">
    <input type="text" placeholder="PSČ" name="tab[psc_uzivatele]" value="<?=@$udb['psc_uzivatele']?>">
    <select name="tab[stat_uzivatele]">
      <option value="1" <?=@$udb['stat_uzivatele']==1?'selected':''?>>Česká republika</option>
      <option value="2" <?=@$udb['stat_uzivatele']==2?'selected':''?>>Slovenská republika</option>
      <option value="-1" <?=@$udb['stat_uzivatele']==-1?'selected':''?>>(jiný stát)</option>
    </select>
    <br>
    <input type="text" placeholder="Telefon" name="tab[telefon_uzivatele]" value="<?=@$udb['telefon_uzivatele']?>">
    <input type="text" placeholder="Datum narození jako 1.1.1990" name="datumNarozeni" value="<?=$u?$u->datumNarozeni()->format('j.n.Y'):''?>" >
    <input type="password" placeholder="Heslo" name="heslo2">
    <input type="password" placeholder="Heslo pro kontrolu" name="heslo3">
    <br>
    <img src="<?=$avatar?>" class="avatar">
    Obrázek uživatele (vyberte, pokud chcete změnit):<br>
    <input type="file" name="obrazek">
    <br style="clear:both"><br>
    <?php if($u){ ?>
    <input type="submit" name="upravit" value="Upravit">
    <?php } ?>
    <?php if(!$u && !$pokracovat){ ?>
    <input type="submit" name="registrovat" value="Registrovat">
    <?php } ?>
    <?php if(!$u && REGISTRACE_AKTIVNI){ ?>
    <input type="submit" name="registrovatAPrihlasit" value="Přihlásit na GameCon"><br>
    <?php } ?>
  </div>
</form>
</div>
