<?php

if(!$u){ echo hlaska('jenPrihlaseni'); return; } //jen přihlášení
if(!REGISTRACE_AKTIVNI){ echo hlaska('prihlaseniVypnuto'); return; } //reg neaktivní

if(!$u->gcPrihlasen())
  return; //přehled vidí jen přihlášení na gc

$fin=$u->finance();
$veci=$u->finance()->prehledHtml();
$slevyA='<li>'.implode('<li>',$u->finance()->slevyAktivity());
$slevyV='<li>'.implode('<li>',$u->finance()->slevyVse());
$zaplaceno=$u->finance()->stav()>=0;
$limit=false;

$a=$u->koncA();
$uid=$u->id();

if(!$zaplaceno)
{
  $castka=-$fin->stav();
  $pozde=-round($fin->stavPozde());
  if(SLEVA_AKTIVNI)
    $limit=datum3(SLEVA_DO);
  if($u->stat()=='CZ')
    $castka.='&thinsp;Kč' xor
    $pozde.='&thinsp;Kč';
  elseif($u->stat()=='SK')
    $castka=round($castka/KURZ_EURO,2).'&thinsp;€' xor
    $pozde=round($castkaPozde/KURZ_EURO,2).'&thinsp;€';
}

?>



<h1>Přehled financí</h1>
<p>V následujícím přehledu vidíš seznam všech položek, které sis na GameConu objednal<?=$a?>, s výslednými cenami po započítání všech slev. Pokud je tvůj celkový stav financí záporný, pokyny k <b>zaplacení</b> najdeš <a href="#placeni">úplně dole</a>.</p>


<style> 
.tabVeci table { border-collapse: collapse; }
.tabVeci table td { border-bottom: solid 1px #ddd; padding-right: 5px; }
.tabVeci table td:last-child { width: 20px; } 
</style>
<div style="float:left;width:230px"  class="tabVeci">
<h2>Objednané věci</h2>
<?=$veci?>
</div>

<div style="float:right;width:220px">
<h2>Slevy</h2>
<strong>Použité slevy na aktivity</strong>
<ul><?=$slevyA?></ul>
<strong>Další bonusy</strong>
<ul><?=$slevyV?></ul>
</div>

<div style="clear:both"></div>

<h2><a name="placeni">Platba</a></h2>
<?php if(!$zaplaceno){ ?>
  <br>
  <?php if($u->stat()=='CZ'){ ?>
    <strong>Číslo účtu:</strong> <?=UCET_CZ?>
  <?php }else{ ?>
    <strong>Číslo účtu pro SR:</strong> <?=UCET_SK?> (Platba v Eurech)
  <?php } ?><br>
  <strong>Variabilní symbol:</strong> <?=$uid?><br>
  <strong>Částka k zaplacení:</strong> <?=$castka?>
  <?php if($limit){ ?>
    do <?=$limit?> (<?=$pozde?> později)
  <?php } ?>
  <br><br>
  
  <p>GameCon můžeš nyní <strong>zaplatit převodem</strong> na účet uvedený níž. Jako variabilní symbol slouží tvoje id: <?=$uid?>. 
  <?php if($limit){ ?>
    Při platbě <strong>do <?=$limit?></strong> platíš celkem <strong><?=$castka?></strong>, při pozdější platbě beze slevy nebo na místě pak <?=$pozde?>. Počítá se datum, kdy peníze dorazí na účet GC, mezibankovní převod může trvat až 2 dny.
    <br><br>Při plánování aktivit si na účet pošli klidně více peněz. Přebytek ti vrátíme na infopultu nebo ho můžeš využít k přihlašování uvolněných aktivit na místě.
  <?php }else{ ?>
    Období pro slevu za včasnou platbu vypršelo, zaplatit tedy můžeš převodem nebo na místě celkem <strong><?=$castka?></strong>.
  <?php } ?>
  </p>
<?php }else{ ?>
  <p>Všechny tvoje pohledávky jsou <strong style="color:green">v pořádku zaplaceny</strong>, není potřeba nic platit. Pokud si ale chceš dokupovat aktivity na místě se slevou nebo bez nutnosti používat hotovost, můžeš si samozřejmě kdykoli převést peníze do zásoby na:</p>
  <?php if($u->stat()=='CZ'){ ?>
    <strong>Číslo účtu:</strong> <?=UCET_CZ?>
  <?php }else{ ?>
    <strong>Číslo účtu pro SR:</strong> <?=UCET_SK?> (Platba v Eurech)
  <?php } ?><br>
  <strong>Variabilní symbol:</strong> <?=$uid?><br>
<?php } ?>






