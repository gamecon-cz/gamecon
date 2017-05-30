<?php

if(!$u) { //jen přihlášení
  echo hlaska('jenPrihlaseni');
  return;
}
if(!$u->gcPrihlasen() || !FINANCE_VIDITELNE) return; //přehled vidí jen přihlášení na gc

$fin=$u->finance();
$veci=$u->finance()->prehledHtml();
$slevyA = array_flat('<li>', $u->finance()->slevyAktivity(), '</li>');
$slevyV = array_flat('<li>', $u->finance()->slevyVse(), '</li>');
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
  if($u->stat()=='CZ') {
    $castka.='&thinsp;Kč';
    $pozde.='&thinsp;Kč';
  } else {
    $castka=round($castka/KURZ_EURO,2).'&thinsp;€';
    $pozde=round($pozde/KURZ_EURO,2).'&thinsp;€';
  }
}

?>



<h1>Přehled financí</h1>
<p>V následujícím přehledu vidíš seznam všech položek, které sis na GameConu objednal<?=$a?>, s výslednými cenami po započítání všech slev. Pokud je tvůj celkový stav financí záporný, pokyny k <b>zaplacení</b> najdeš <a href="finance#placeni">úplně dole</a>.</p>


<style> 
.tabVeci table { border-collapse: collapse; }
.tabVeci table td { border-bottom: solid 1px #ddd; padding-right: 5px; }
.tabVeci table td:last-child { width: 20px; } 
</style>
<div style="float:left;width:250px;margin-bottom:24px"  class="tabVeci">
<h2>Objednané věci</h2>
<?=$veci?>
</div>

<div style="float:right;width:250px">
  <h2>Slevy</h2>
  <?php if($slevyA) { ?>
    <strong>Použité slevy na aktivity</strong>
    <ul><?=$slevyA?></ul>
  <?php } ?>
  <?php if($slevyV) { ?>
    <strong>Další bonusy</strong> (pokud si je objednáš)
    <ul><?=$slevyV?></ul>
  <?php } ?>
</div>

<div style="clear:both"></div>

<?php if(!$zaplaceno){ ?>
  <p>
  <?php if($u->stat()=='CZ'){ ?>
    <h2 id="placeni">Platba</h2>
    <strong>Číslo účtu:</strong> <?=UCET_CZ?><br>
    <strong>Variabilní symbol:</strong> <?=$uid?><br>
  <?php }else{ ?>
    <h2 id="placeni">Platba (SEPA)</h2>
    <strong>IBAN:</strong> <?=IBAN?><br>
    <strong>BIC/SWIFT:</strong> <?=BIC_SWIFT?><br>
    <strong>Poznámka pro příjemce:</strong> /VS/<?=$uid?> <i>(vč. lomítek)</i><br>
  <?php } ?>
  <strong>Částka k zaplacení:</strong> <?=$castka?>
  <?php if($limit){ ?>
    do <?=$limit?> <i>(<?=$pozde?> později)</i>
  <?php } ?>
  </p>

  <?php if($limit){ ?>
    <?php if($u->stat()=='CZ'){ ?>
      <p>GameCon je nutné zaplatit převodem <strong>do <?=$limit?></strong>. Platíš celkem <strong><?=$castka?></strong>, variabilní symbol je tvoje ID <strong><?=$uid?></strong>.</p>
    <?php }else{ ?>
      <p>GameCon je nutné zaplatit převodem <strong>do <?=$limit?></strong>. Platíš celkem <strong><?=$castka?></strong>, přesné údaje o platbě nalezneš výše.</p>
    <?php } ?>
    <ul class="seznam-bez-okraje">
    <li class="poznamka">Při pozdější platbě tě systém dne <strong>1.7</strong> (příp. 16.7. při pozdější přihlášce)<strong> automaticky odhlásí</strong>.</li>
    <li class="poznamka">Pokud si aktivity (znovu) přihlásíš po 30.6., beze slevy nebo na místě pak platíš <?=$pozde?>.</li>
    <li class="poznamka">Při plánování aktivit si na účet pošli klidně více peněz. Přebytek ti vrátíme na infopultu nebo ho můžeš využít k přihlašování uvolněných aktivit na místě.</li>
    </ul>
  <?php }else{ ?>
    <p>Období pro slevu za včasnou platbu vypršelo, zaplatit tedy můžeš převodem nebo na místě celkem <strong><?=$castka?></strong>.</p>
  <?php } ?>
<?php }else{ ?>
  <?php if($u->stat()=='CZ'){ ?>
    <h2 id="placeni">Platba</h2>
    <p>Všechny tvoje pohledávky jsou <strong style="color:green">v pořádku zaplaceny</strong>, není potřeba nic platit. Pokud si ale chceš dokupovat aktivity na místě se slevou nebo bez nutnosti používat hotovost, můžeš si samozřejmě kdykoli převést peníze do zásoby na:</p>
    <strong>Číslo účtu:</strong> <?=UCET_CZ?><br>
    <strong>Variabilní symbol:</strong> <?=$uid?><br>
  <?php }else{ ?>
    <h2 id="placeni">Platba (SEPA)</h2>
    <p>Všechny tvoje pohledávky jsou <strong style="color:green">v pořádku zaplaceny</strong>, není potřeba nic platit. Pokud si ale chceš dokupovat aktivity na místě se slevou nebo bez nutnosti používat hotovost, můžeš si samozřejmě kdykoli převést peníze do zásoby na:</p>
    <strong>IBAN:</strong> <?=IBAN?><br>
    <strong>BIC/SWIFT:</strong> <?=BIC_SWIFT?><br>
    <strong>Poznámka pro příjemce:</strong> /VS/<?=$uid?> <i>(vč. lomítek)</i><br>
  <?php } ?>
<?php } ?>
