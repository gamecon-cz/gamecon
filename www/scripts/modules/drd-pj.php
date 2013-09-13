<?php

/**
 * Seznam PJů na DrD generovaný z databáze
 */

function fotka($jmeno){
  $fotka=$jmeno;
  $fotka=substr($fotka,0,strpos($fotka,' '));
  $fotka=strtolower($fotka);
  $fotka=strtr($fotka,array('č'=>'c','á'=>'a'));
  return URL_WEBU.'/files/systemove/pj/'.$fotka.'.jpg'; 
}

$o=dbQuery('
  SELECT * FROM drd_pj WHERE rok='.ROK.' ORDER BY jmeno_pj
');
$pjove=array();
while($r=mysql_fetch_array($o))
{
  $pjove[]=array_merge(
    $r,
    array(
      'arpav'=>explode(',',$r['arpav']),
      'obrazek'=>fotka($r['jmeno_pj']),
      'o_sobe'=>$r['o_sobe']?Markdown($r['o_sobe']):'',
      'herni_profil'=>$r['herni_profil']?Markdown($r['herni_profil']):'',
    )
  );
}

?>



<h1>PJové mistrovství v DrD</h1>

<?php foreach($pjove as $pj){ ?>
<div class="organizatori">
  
  <h3><?=$pj['jmeno_pj']?></h3>
  <div class="foto">
    <img src="<?=$pj['obrazek']?>" />
  </div>
  
  <strong>Funkce na GC</strong><br>
  pán jeskyně<br>
  
  <?php if($pj['o_sobe']){ ?>
    <a href="#" onclick="$(this).siblings('.oSobe').fadeToggle();return false"><b>O sobě píše</b></a> -
  <?php } ?> 
  <?php if($pj['herni_profil']){ ?>
    <a href="#" onclick="$(this).siblings('.herniProfil').fadeToggle();return false"><b>Herní profil</b></a><br>
  <?php } ?>
  
  <div class="oSobe" style="display:none"> <?=$pj['o_sobe']?> </div>
  
  <?php if($pj['herni_profil']){ ?>
  <div class="herniProfil" style="display:none">
    <div class="pjove">
      <div class="staty">
        <div class="text">Akce:</div>
        <div class="radek">
          <?php for($i=0;$i<$pj['arpav'][0];$i++){ ?>   <img src=/files/styly/styl-aktualni/li-cervena2.gif>   <?php } ?>
        </div>
        <div class="text">Roleplaying:</div>
        <div class="radek">
          <?php for($i=0;$i<$pj['arpav'][1];$i++){ ?>   <img src=/files/styly/styl-aktualni/li-cervena2.gif>   <?php } ?>
        </div>
        <div class="text">Pravidla:</div>
        <div class="radek">
          <?php for($i=0;$i<$pj['arpav'][2];$i++){ ?>   <img src=/files/styly/styl-aktualni/li-cervena2.gif>   <?php } ?>
        </div>
        <div class="text">Atmosféra:</div>
        <div class="radek">
          <?php for($i=0;$i<$pj['arpav'][3];$i++){ ?>   <img src=/files/styly/styl-aktualni/li-cervena2.gif>   <?php } ?>
        </div>
        <div class="text">Vtip:</div>
        <div class="radek">
          <?php for($i=0;$i<$pj['arpav'][4];$i++){ ?>   <img src=/files/styly/styl-aktualni/li-cervena2.gif>   <?php } ?>
        </div>
      </div>
      <div class="popis"><?=$pj['herni_profil']?></div>
    </div>
  </div>
  <?php } ?>
  
  <div style="clear:both"></div>
</div>
<?php } ?>


