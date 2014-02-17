<?php

/** 
 * Stránka pro přehled všech přihlášených na aktivitu DrD. DrD je starý modul a 
 * přes všechnu snahu je na pozadí black magic! 
 *
 * nazev: Seznam na DrD
 * pravo: 102
 */
?>




Tato část nefunguje z důvodu převodu DrD na univerzální systém teamových aktivit.
<?php return; ?>




<?php

define('ZRUS_SEMIFINALE',-1); //pseudo ID aktivit, pokud je chceme rušit a ne přihlašovat
define('ZRUS_FINALE',-2);

// Zpracování požadavků JSONem
if(!empty($_POST['druzina']))
{
  $akt=$_POST['aktivita'];
  $hraci=explode(',',dbOneCol('SELECT GROUP_CONCAT(id_uzivatele) FROM drd_uzivatele_druziny WHERE rok='.ROK.' AND id_druziny='.(int)$_POST['druzina']));
  if($akt==ZRUS_SEMIFINALE || $akt==A_DRD_SEMIFINALE1 || $akt==A_DRD_SEMIFINALE2)
  { // zrušit semifinále (i při přihlašování semifinále pro případ změny jedné varianty semifinále na druhou)
    dbQuery('DELETE FROM akce_prihlaseni WHERE id_uzivatele IN('.implode(',',$hraci).') AND ( id_akce='.A_DRD_SEMIFINALE1.' OR id_akce='.A_DRD_SEMIFINALE2.' )');
    $o='nepostupují';
  }
  if($akt==ZRUS_FINALE)
  {
    dbQuery('DELETE FROM akce_prihlaseni WHERE id_uzivatele IN('.implode(',',$hraci).') AND id_akce='.A_DRD_FINALE);
    $o='nepostupují';
  }
  if($akt==A_DRD_FINALE || $akt==A_DRD_SEMIFINALE1 || $akt==A_DRD_SEMIFINALE2)
  {
    $aktivita=Aktivita::zId((int)$akt);
    $aktivita->prihlasPrepisHromadne($hraci);
    if($akt==A_DRD_SEMIFINALE2) $o='odpoledne';
    elseif($akt==A_DRD_SEMIFINALE1 || $akt==A_DRD_FINALE) $o='ráno';
    else $o='CHYBA';
  }
  //výstup
  echo json_encode(array('zprava'=>
    empty($o) ? print_r($_POST,true).'<br>' : $o 
  ));
  exit();
}

require_once('../../../'.ADMIN_WWW_CESTA.'/scripts/modules/drd-konstanty.hhp'); // hack, rozhraní $DRD_RASA apod. 

$o=dbQuery('
  SELECT 
    p.id_uzivatele,
    h.login_uzivatele as login,
    CONCAT(h.jmeno_uzivatele," ",h.prijmeni_uzivatele) as jmeno,
    IFNULL(ch.rasa,0) rasa,
    IFNULL(ch.povolani,0) povolani,
    d.nazev, 
    d.id_druziny, 
    s.login_uzivatele as spravce,
    d.blok,
    pj.jmeno_pj,
    d.poznamka,
    sobota.id_akce as sobota,
    nedele.id_akce as nedele 
  FROM akce_prihlaseni p
  LEFT JOIN drd_uzivatele_druziny du ON(p.id_uzivatele=du.id_uzivatele AND du.rok='.ROK.')
  LEFT JOIN drd_druziny d ON(d.id_druziny=du.id_druziny)
  LEFT JOIN uzivatele_hodnoty h ON(p.id_uzivatele=h.id_uzivatele)
  LEFT JOIN uzivatele_hodnoty s ON(d.spravce=s.id_uzivatele)
  LEFT JOIN drd_pj pj ON(pj.blok1=d.id_druziny OR pj.blok2=d.id_druziny OR pj.blok3=d.id_druziny)
  LEFT JOIN drd_postava ch ON(ch.rok='.ROK.' AND ch.id_uzivatele=p.id_uzivatele)
  LEFT JOIN akce_prihlaseni sobota ON( ( sobota.id_akce='.A_DRD_SEMIFINALE1.' OR sobota.id_akce='.A_DRD_SEMIFINALE2.' ) AND sobota.id_uzivatele=p.id_uzivatele )
  LEFT JOIN akce_prihlaseni nedele ON( nedele.id_akce='.A_DRD_FINALE.' AND nedele.id_uzivatele=p.id_uzivatele )
  WHERE p.id_akce='.ID_AKTIVITA_DRD.'
  ORDER BY d.id_druziny, p.id_uzivatele
  ');
$druziny=array();
while($r=mysql_fetch_array($o))
{
  $cislo=$r['id_druziny']?$r['id_druziny']-DRD_POSUN:0;
  $druziny[$cislo]['hraci'][]=$r;
  $druziny[$cislo]['id_druziny']=$r['id_druziny'];
  $druziny[$cislo]['nazev']=$r['nazev'];
  $druziny[$cislo]['spravce']=$r['spravce'];
  $druziny[$cislo]['blok']=$r['blok']==1?'ráno':($r['blok']==2?'odpoledne':($r['blok']==3?'večer':'nevybrán'));
  $druziny[$cislo]['pj']=$r['jmeno_pj'];
  $druziny[$cislo]['poznamka']=$r['poznamka'];
  $druziny[$cislo]['sobota']=$r['sobota'];
  $druziny[$cislo]['nedele']=$r['nedele'];
  if(!$cislo)
    $druziny[$cislo]['nazev']='(nezařazení)';
}
 
 
?>



<script>
$(function(){
  $('form.vyberDne [type=submit]').click(function(){
    var druzina=$(this).siblings('[name=druzina]').val();
    var form=$(this).closest('form').serialize();
    var policko=$(this).closest('form').prevAll('.stav').first();
    form+='&aktivita='+$(this).attr('name');
    if(druzina)
    {
      policko.html('loading…');
      $.post(document.URL,form,function(data){
        //alert(data.zprava);
        policko.html(data.zprava);
      },"json");
    }
    return false;
  });
});
</script>

<style>
  .vyberDne { position: absolute; left: 150px; }
  form.vyberDne input[type="submit"] { width: 60px; }
  .vyberDne.sobota { margin-top: -1px; }
  .vyberDne.nedele { margin-top: 1px; }
</style>

<h1>Seznamy přihlášených a družin na DrD</h1>

<em>Volba bloků automaticky odhlásí všem členům družiny všechny překrývající se aktivity!</em><br>
<em>Z přihlášeného semifinále a finále se dá jednotlivě odhlásit jen ručním zásahem. (Hromadně pomocí tlačítka „nepostup“)</em><br>

<?php foreach($druziny as $cislo => $druzina){ ?>
  
  <h2>č. <?=$cislo.' – '.$druzina['nazev']?></h2>
  
  <strong>Správce:</strong>       <?=$druzina['spravce']?>  <br>
  <strong>Pán jeskyně:</strong>   <?=$druzina['pj']?>       <br>
  <strong>Poznámka:</strong>      <?=$druzina['poznamka']?> <br>
  <strong>Pátek:</strong>         <?=$druzina['blok']?>     <br>
  <strong>Sobota:</strong>
    <span class="stav">
    <?= $druzina['sobota']==A_DRD_SEMIFINALE1 ? 'ráno' : '' ?>
    <?= $druzina['sobota']==A_DRD_SEMIFINALE2 ? 'odpoledne' : '' ?>
    <?= $druzina['sobota']==null ?              'nepostupují' : '' ?>
    </span>
    <form class="radkovy vyberDne sobota">
    <input type="hidden" name="druzina" value="<?=$druzina['id_druziny']?>">
    <input type="submit" name="<?=A_DRD_SEMIFINALE1?>" value="ráno">
    <input type="submit" name="<?=A_DRD_SEMIFINALE2?>" value="odpoledne">
    <input type="submit" name="<?=ZRUS_SEMIFINALE?>" value="nepostup"> </form>
    <br>
  <strong>Neděle:</strong>
    <span class="stav">
    <?= $druzina['nedele']==A_DRD_FINALE ? 'ráno' : '' ?>
    <?= $druzina['nedele']==null ?         'nepostupují' : '' ?>
    </span>
    <?php if($druzina['sobota']){ ?>
    <form class="radkovy vyberDne nedele">
    <input type="hidden" name="druzina" value="<?=$druzina['id_druziny']?>">
    <input type="submit" name="<?=A_DRD_FINALE?>" value="ráno">
    <input type="submit" name="<?=ZRUS_FINALE?>" value="nepostup"> </form>
    <?php } ?>
    <br>
  <br>
  
  <table>
  <tr>
    <th>ID</th>
    <th>Login</th>
    <th>Jméno</th>
    <th>Rasa</th>
    <th>Povolání</th>
  </tr>
  <?php foreach($druzina['hraci'] as $hrac){ ?>
  <tr>
    <td><?=$hrac['id_uzivatele']?></td>
    <td><?=$hrac['login']?></td>
    <td><?=$hrac['jmeno']?></td>
    <td><?=$DRD_RASA[$hrac['rasa']]?></td>
    <td><?=$DRD_POVOLANI[$hrac['povolani']]?></td>
  </tr>
  <?php } ?>
  </table><br>
  
<?php } ?>


