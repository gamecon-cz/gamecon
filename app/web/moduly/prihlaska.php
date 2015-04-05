<?php

if(GAMECON_BEZI || $u && $u->gcPritomen()) {
  echo hlaska('prihlaseniJenInfo');
  return;
}

if(!REGISTRACE_AKTIVNI) {
  echo hlaska('prihlaseniVypnuto');
  return;
}

if(!$u) exit(header('Location: '.URL_WEBU.'/registrace?prihlaska'));

$shop=new Shop($u);

if(!empty($_POST)) 
{
  // odhlášení z GameConu
  if(post('odhlasit')) {
    $u->gcOdhlas();
    oznameni(hlaska('odhlaseniZGc',$u));
  }
  // přihlašování nebo editace
  $prihlasovani=false;
  if(!$u->gcPrihlasen())
    $prihlasovani=$u->gcPrihlas();
  $shop->zpracujPredmety();
  $shop->zpracujUbytovani();
  $shop->zpracujSlevy();
  $shop->zpracujJidlo();
  $shop->zpracujVstupne();
  if($prihlasovani)
  {
    $_SESSION['ga_tracking_prihlaska']=true; //hack pro zobrazení js kódu úspěšné google analytics konverze
    oznameni(hlaska('prihlaseniNaGc',$u));
  }
  else
    oznameni(hlaska('aktualizacePrihlasky')); 
}

// hack pro zobrazení js kódu úspěšné google analytics konverze
if(isset($_SESSION['ga_tracking_prihlaska'])){
  $gaTrack="<script>_gaq.push(['_trackEvent', 'gamecon', 'prihlaseni']);</script>";
  unset($_SESSION['ga_tracking_prihlaska']);
}else
  $gaTrack='';

$ubytovani=$shop->ubytovaniHtml();
$predmety=$shop->predmetyHtml();
$slevy=$shop->slevyHtml();
$jidlo=$shop->jidloHtml();

$a=$u->koncA();

?>



<h1>Přihláška na GameCon</h1>

<?=$gaTrack?>

<form method="post" class="obecny">

  <?php if($u->gcPrihlasen()){ ?>
  <p>Jsi přihlášen<?=$a?> na GameCon <?=ROK?>. Níž si můžeš upravit svou přihlášku, tj. objednané ubytování a předměty až téměř do začátku GameConu.</p>
  <?php }else{ ?>
  <p>Vyplněním údajů se přihlásíš na GameCon <?=ROK?>. Můžeš si vybrat předměty a ubytování s GameCon tématikou, které budeš chtít. Výber jde i později změnit.</p>
  <?php } ?> 
  
  <h2>Předměty</h2>
  
  <div style="width:200px; float:right; margin-left:30px;">
    <a href="files/obsah/materialy/2014/kostka.jpg" rel="lightbox" title="Kostka">
    <img src="files/obsah/materialy/2014/kostka_m.jpg" style="height:75px;width:200px"></a>
    <a href="files/obsah/materialy/2014/placka.jpg" rel="lightbox" title="Placka">
    <img src="files/obsah/materialy/2014/placka_m.jpg" style="height:75px;width:200px"></a>
    <a href="files/obsah/materialy/2014/tricko.jpg" rel="lightbox" title="Tričko">
    <img src="files/obsah/materialy/2014/tricko_m.jpg" style="height:75px;width:200px"></a>
  </div>
  
  <?=$predmety?><br>
  <p>Placek, kostek i triček s logem GameConu si můžeš objednat více. Stačí kliknout na tlačítko + nebo u triček vyplnit jedno a po potvrzení formuláře se ti nabídne položka pro výběr dalšího.</p>
  
  <h2>Jídlo</h2>
  <?=$jidlo?>
  
  <h2>Ubytování</h2>
  <?=$ubytovani?>

  <h2>Dobrovolné vstupné</h2>
  <?=$shop->vstupneHtml()?>
  
  <h2>Slevy</h2>
  <p>Můžeš získat následující slevy <b>na aktivity</b> (sčítají se):</p>
  <?=$slevy?><br><br>
  
  <input type="submit" name="wut" value="<?=$u->gcPrihlasen()?'Uložit změny':'Přihlásit na GameCon'?>">
  <?php if($u->gcPrihlasen()){ ?>
  <input type="submit" name="odhlasit" value="Odhlásit se z GameConu" onclick="return confirm('Odhlášení z GameConu zruší všechny tvé registrace na aktivity a nákupy předmětů. Kliknutím na OK se odhlásíš.')">
  <?php } ?>
  
</form>
