<?php

echo 'minihra neběží';
return;

$O_OBRAZEK='Příběhy impéria';
$C_SIFRA='Pdnodiušntěpnpáívečnše, cvžhumcdéevéš vwpědšdmuěšjt, kšědopo zvípýrůjosdíovábpoikrl  ajýpgroačkbratslířywepšásqéu? Trpežánkuhstlgie vcloškblmegájskduta mává vuz  Mtyočghujyajlzávodirvlrrlciu nriěčicjzo ppjorád psjarolděcveejčm asp pklovohanydibníušijpke sene vod čkaeřernávlpečřnliý. Ale Babirkg Býtotasmas nema nývěltj vruymápycspíabál strlleudůšrankáovlu oévdhámdiěvrnvsu. Tětažek hamoredkřnově zčldlaanoršíu.';
$O_SIFRA='Poutníče, chceš vědět, kdo způsobil apokalypsu? Tenhle vobejda má v Mohylvillu něco pod palcem a pohybuje se v červený. A Big Boss na něj vypsal slušnou odměnu. Tak hodně zdaru.';

/**
 * Vrátí "hrubý text" z řetězce (tj. bez mezer a diakritiky, malým)
 */
function hrubyText($spravne)
{
  $spravne=strtolower($spravne);
  $spravne=strtr(
    mb_convert_encoding($spravne,'ISO-8859-2','UTF-8'),
    mb_convert_encoding("ÁÄČÇĎÉĚËÍŇÓÖŘŠŤÚŮÜÝŽáäčçďéěëíňóöřšťúůüýž",'ISO-8859-2','UTF-8'),
    mb_convert_encoding("AACCDEEEINOORSTUUUYZaaccdeeeinoorstuuuyz",'ISO-8859-2','UTF-8'));
  $spravne=preg_replace( '/\s+/','',$spravne);
  return($spravne);
}

/**
 * Rozhodne, v kolika znacích se řetězce shodnou
 */
function shoda($a,$b)
{
  $j=strlen($a);
  $pocet=0;
  for($i=0;$i<$j;$i++)
  {
    if(substr($a,$i,1) === substr($b,$i,1)) // chytá jiné znaky i rozdíly délky
      $pocet++;
  }
  return $pocet;
}  

/**
 * Rozhodne, jestli odpověď dostatečně odpovídá cílovému textu
 */
function sifraSpravne($spravne,$tip)
{
  return strlen(hrubyText($spravne))-6 <= shoda(hrubyText($spravne),hrubyText($tip));
} 

// Zpracování post formulářů
if($_POST)
{
  if($_POST['predmety'])
    dbQueryS('INSERT INTO minihra(id_uzivatele,predmety) VALUES ($0,$1)',array($u->id(),$_POST['predmety']));
  if($_POST['pribeh'])
    dbQueryS('UPDATE minihra SET pribeh=$0, pribeh_poradi=(SELECT IF(MAX(pribeh_poradi)>0,MAX(pribeh_poradi)+1,1) FROM (SELECT pribeh_poradi FROM minihra) subsel) WHERE id_uzivatele=$1',array($_POST['pribeh'],$u->id()));
  if($_POST['obrazek'])
    if(mb_strtolower($_POST['obrazek'])==mb_strtolower($O_OBRAZEK))
      dbQuery('UPDATE minihra SET obrazek=1 WHERE id_uzivatele='.$u->id()) xor
      oznameni('správně');
    else
      chyba('špatně');
  if($_POST['sifra'])
    if(sifraSpravne($O_SIFRA,$_POST['sifra']))
      dbQuery('UPDATE minihra SET sifra=1 WHERE id_uzivatele='.$u->id()) xor
      oznameni('správně');
    else
      chyba('špatně');
  back();
}


$splnene=-1;
if($u)
{
  $hra=dbOneLine('SELECT * FROM minihra WHERE id_uzivatele='.$u->id());
  $splnene=0;
  if($hra['predmety'])
    $predmety=$hra['predmety'] xor
    $splnene++ xor
    $pribeh=current(dbOneLine('SELECT GROUP_CONCAT( IF(id_uzivatele='.$u->id().', CONCAT("<b>",pribeh,"</b>") ,pribeh) ORDER BY pribeh_poradi SEPARATOR " ") FROM minihra')); //musíme načíst příběh už teĎ
  if($hra['pribeh'])    $splnene++;
  if($hra['obrazek'])   $splnene++;
  if($hra['sifra'])     $splnene++;
  if($hra['facebook'])  $splnene++;
}

$a=$u?$u->koncA():'';
$zbyva=5-$splnene;

?>



<img src="files/obsah/obrazky/radio.png" style="position:absolute;margin-left:270px;height:120px">
<h1>Pět krůčků k Mohylvillu</h1>
<p>Poutníče, rozhodl ses vydat do osady Mohylvill? Naměřili jsme u tebe příliš vysokou radiaci z okolního vyprahlého světa.</p>
<p>Chceš ji snížit? Čeká na tebe <b>pět jednoduchých úkolů</b>. Když je splníš, získáš navíc výhodu do hry Mohylvill a zařadíš se do <b>slosování o deskovku</b>. Takže začínáme ...</p>
<?php if(!$u){ ?>
  <p style="text-align:center;font-style:italic;font-weight:bold;color:#88000f;font-size:11pt;margin-top:20px;">Pro spuštění hry se musíš přihlásit vpravo nahoře.</p>
<?php } ?>
<br>
<hr width=100%>
<br>
<div class="minihra">
  
  <?php if($splnene-- >= 0){ ?>
  <div class="ukol <?=$splnene>=0?'splneny':''?> ">
    <hr class="milnik">
    <p>Napiš tři předměty, které by sis s sebou určitě vzal<?=$a?> do postapokyliptické pustiny.</p>
    <?php if($splnene>=0){ ?>
      <input type="text" name="predmety" value="<?=$predmety?>" disabled>
    <?php }else{ ?>
      <form method="post">
      <input type="text" name="predmety">
      <input type="submit" value="Odeslat">
      </form>
    <?php } ?>
  </div>
  <?php } ?>
  
  <?php if($splnene-- >= 0){ ?>
  <div class="ukol <?=$splnene>=0?'splneny':''?> ">
    <hr class="milnik">
    <p>Vytvořme příběh hrdiny z prostředí po apokalypse. Napiš tři slova, aby navazovaly na již vytvořený příběh níže.</p>
    <p>
      <?php if($splnene>=0){ ?>
        <?=$pribeh?>
      <?php }else{ ?>
        <form method="post">
        <?=$pribeh?>
        <input type="text" name="pribeh">
        <input type="submit" value="Odeslat">
        </form>
      <?php } ?>
    </p>
  </div>
  <?php } ?>
  
  <?php if($splnene-- >= 0){ ?>
  <div class="ukol <?=$splnene>=0?'splneny':''?> ">
    <hr class="milnik">
    <p>Poznej obrázek, z jaké RPG hry pochází?</p>
    <img src="files/obsah/obrazky/minihra-poznej.jpg" style="width:220px;margin-left:-10px;"><br><br>
    <?php if($splnene>=0){ ?>
      <input type="text" name="obrazek" value="<?=$O_OBRAZEK?>" disabled><br><br>
      <p>Nezapomeň, že na letošním GameConu bude první ročník klání v Příbězích Impéria. Jmenuje se <a href="rpg/legendy-klubu-dobrodruhu">Legeny Klubu dobrodruhů.</a></p>
    <?php }else{ ?>
      <form method="post">
      <input type="text" name="obrazek">
      <input type="submit" value="Odeslat">
      </form>
    <?php } ?>
  </div>
  <?php } ?>
  
  <?php if($splnene-- >= 0){ ?>
  <div class="ukol <?=$splnene>=0?'splneny':''?> ">
    <hr class="milnik">
    <p>Rozlušti šifru. Najdeš v ní nápovědu do hry Mohylvill a za úspěšné rozluštění získáš první kartu:</p>
    <p style="font-style: italic;"><?=$C_SIFRA?></p>
    <?php if($splnene>=0){ ?>
      <textarea style="width:224px;margin-left:-15px;height:80px" name="sifra" disabled><?=$O_SIFRA?></textarea>
    <?php }else{ ?>
      <form method="post">
      <textarea style="width:224px;margin-left:-15px;height:80px" name="sifra"></textarea>
      <input type="submit" value="Odeslat">
      </form>
    <?php } ?>
  </div>
  <?php } ?>
  
  <?php if($splnene-- >= 0){ ?>
  <div class="ukol <?=$splnene>=0?'splneny':''?> ">
    <hr class="milnik">
    <p>Pošli nám na <a href="http://www.facebook.com/gamecon" target="_blank">facebook</a> svoji fotku s nápisem „Kašlu na *doplň*, jedu na GameCon.“ Tématické fotky (např. v plynové masce) vítány :-)</p>
    <span class="hinted" style="font-size:80%;color:#888">Nemám facebook
      <span class="hint">Pošli nám ji alespoň na mail info@gamecon.cz</span>
    </span>

  </div>
  <?php } ?>
  
  <?php while($zbyva-- > 1){ ?>
  <div class="ukol skryty">
    <hr class="milnik">
    <img src="files/styly/styl-aktualni/otaznik.png">
  </div>
  <?php } ?>
  
  <!-- dekorace -->
  
  <hr class="linka">
  <hr class="milnik posledni">
  
</div>
