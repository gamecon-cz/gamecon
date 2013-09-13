<?php

/** 
 * Stránka pro tvorbu a editaci aktivit. Brand new.
 *
 * nazev: Nová aktivita
 * pravo: 102
 */

if(Aktivita::editorTestJson())    // samo sebe volání ajaxu
  die(Aktivita::editorChybyJson());
if($a=Aktivita::editorZpracuj())  // úspěšné uložení změn ve formuláři
  if($a->nova())
    back('/aktivity/upravy?aktivitaId='.$a->id());
  else
    back();
$a=Aktivita::zId(get('aktivitaId'));  // načtení aktivity podle předaného ID
$editor=Aktivita::editor($a);         // načtení html editoru aktivity

?>



<form method="post" enctype="multipart/form-data">
  <?=$editor?>
</form>











<?php

return;

$tpl=new XTemplate('upravy.xtpl');

if(post('ulozit') || post('ulozitAEditovat'))
{ //ukládání aktivity
  $idAkce=$_POST['fields']['id_akce'];
  if(!$_POST['fields']['id_akce']) //zrušení id pokud vkládáme novou akci
    unset($_POST['fields']['id_akce']);
  $_POST['fields']['rok']=ROK;
  if(maVolno($_POST['fields']['organizator'],$_POST['fields'],
    isset($_POST['fields']['id_akce'])?$_POST['fields']['id_akce']:null))
  {
    if(!$_POST['fields']['patri_pod']) //aktivita má jedinou instanci, přímý update
      dbInsertUpdate('akce_seznam',post('fields'));
    else //aktivita má víc instancí, musíme vybrané položky aktualizovat v mateřské instanci a zde dát null
    {
      $f2=$_POST['fields'];
      $idm=dbOneLineS('SELECT MIN(id_akce) as id_akce FROM akce_seznam WHERE patri_pod=$0',
        array($_POST['fields']['patri_pod']));
      $idm=$idm['id_akce']; //id mateřské instance
      dbInsertUpdate('akce_seznam',array( //update mateřské instance
        'id_akce'=>$idm,
        'url_akce'=>$f2['url_akce'],
        'popis'=>$f2['popis']));
      dbQueryS('UPDATE akce_seznam SET nazev_akce=$1 WHERE patri_pod=$0', //update všech instancí na název 
        array($f2['patri_pod'],$f2['nazev_akce']));
      unset($f2['url_akce'],$f2['popis']); //znulování položek a update skutečně editované instance
      dbInsertUpdate('akce_seznam',$f2);
    }
    if(isset($_FILES['obrazek']['tmp_name'])) //nahrává se obrázek
    {
      $soub='../../../'.ADMIN_WWW_CESTA.'/files/systemove/aktivity/'.$_POST['fields']['url_akce'].'.jpg';
      move_uploaded_file($_FILES['obrazek']['tmp_name'],$soub);
      $obr=imagecreatefromjpeg($soub); // načíst
      $wMax=400;
      if(imagesx($obr)>$wMax) // zmenšit na omezenou max šířku. Todo obecný resampling. Použít nebo je lepší nechat hi-res?
      {
        $ratio=$wMax/imagesx($obr);
        $nobr=imagecreatetruecolor($wMax,imagesy($obr)*$ratio);
        imagecopyresampled($nobr,$obr,
          0,0,  //dst x,y 
          0,0,  //src x,y
          imagesx($nobr),imagesy($nobr), //dst w,h
          imagesx($obr), imagesy($obr)   //scr w,h 
        );
        imagejpeg($nobr,$soub,98); // uložit
      }
    }
    if(post('staraUrl')!=$_POST['fields']['url_akce'])
    { //změnila se url, je potřeba přejmenovat obrázek, pokud existuje
      $cesta='../../../'.ADMIN_WWW_CESTA.'/files/systemove/aktivity/'; //cesta k obrázkům
      if(is_file($cesta.post('staraUrl').'.jpg'))
      {
        $stareAktivity=dbQueryS('SELECT 1 FROM akce_seznam WHERE rok!='.ROK.' AND url_akce=$0',array(post('staraUrl')));
        if(mysql_num_rows($stareAktivity)>0) //danou URL využívá už některá stará aktivita, je potřeba obrázek kopírovat, aby arichvní aktivita neztratila obrázek
          copy($cesta.post('staraUrl').'.jpg',
            $cesta.$_POST['fields']['url_akce'].'.jpg');
        else
          rename($cesta.post('staraUrl').'.jpg',
            $cesta.$_POST['fields']['url_akce'].'.jpg');
      }
    }
    if(post('ulozitAEditovat'))
    {
      if(!$idAkce) // vkládala se nová akce, přesměrovat na její stránku
        back('/aktivity/upravy?aktivitaId='.mysql_insert_id());
      else // přesměrovat tam, odkud jsme přišli
        back();
    }
    else
      back('/aktivity');
  }
  else
  {
    $r=maVolnoKolize();
    $r=$r[0];
    unset($r['id_akce']); //při vkládání nové aktivity nesmíme "propašovat" id_akce do formuláře aby nedošlo k navázání na falešnou (kolizní) aktivitu a její přepsání
    $tpl->assign($r);
    $tpl->assign('cas',datum2($r));
    $tpl->parse('upravy.kolizniAktivity');
  }    
}

if(get('aktivitaId')) //editujeme aktivitu
{
  $aktivita=dbOneLineS('SELECT * FROM akce_seznam a
    LEFT JOIN uzivatele_hodnoty u ON(u.id_uzivatele=a.organizator)
    LEFT JOIN akce_lokace l ON(a.lokace=l.id_lokace)
    LEFT JOIN akce_typy t ON(a.typ=t.id_typu)
    WHERE a.id_akce=$0',array(get('aktivitaId')));
  if($aktivita['patri_pod'])
  { //aktivita má více instancí, sdílené položky musíme načíst s mateřské aktivity
    $mat=dbOneLineS('
      SELECT url_akce, popis 
      FROM akce_seznam 
      WHERE id_akce=(SELECT MIN(id_akce) FROM akce_seznam WHERE patri_pod=$0)',
      array($aktivita['patri_pod']));
    $aktivita=array_merge($aktivita,$mat);
  }
}
elseif(isset($_POST['fields'])) //posílalo se dřív, použijeme poslané hodnoty
  $aktivita=$_POST['fields'];
else //nové hodnoty
  $aktivita=null;

//inicializace políček pro případ nepoužití databáze (tvorby nové aktivity)
$tpl->assign('kapacita',0);
$tpl->assign('kapacita_f',0);
$tpl->assign('kapacita_m',0);
$tpl->assign('cena',0);

$a=dbQuery('SELECT * FROM akce_lokace ORDER BY poradi');
while($r=mysql_fetch_assoc($a))
{
  $tpl->assign('sel',$r['id_lokace']==$aktivita['lokace']?'selected':'');
  $tpl->assign($r);
  $tpl->parse('upravy.lokace');
}

//neurčený den, inicializace dnů
$tpl->assign('sel',0==$aktivita['den']?'selected':'');
$tpl->assign('den',0);
$tpl->assign('denSlovy','(neurčeno)');  
$tpl->parse('upravy.den');
foreach($GLOBALS['PROGRAM_DNY'] as $den=>$denSlovy)
{
  $tpl->assign('sel',$den+$GLOBALS['PROGRAM_DEN_PRVNI']==$aktivita['den']?'selected':'');
  $tpl->assign('den',$den+$GLOBALS['PROGRAM_DEN_PRVNI']);
  $tpl->assign('denSlovy',$denSlovy);  
  $tpl->parse('upravy.den');
}

for($i=$GLOBALS['PROGRAM_ZACATEK']=8;$i<$GLOBALS['PROGRAM_KONEC']=24;$i++)
{
  $tpl->assign('sel',$i==$aktivita['zacatek']?'selected':'');
  $tpl->assign('zacatek',$i);
  $tpl->assign('zacatekSlovy',$i.':00');
  $tpl->parse('upravy.zacatek');
  $tpl->assign('sel',$i==$aktivita['konec']?'selected':'');
  $tpl->assign('konec',$i);
  $tpl->assign('konecSlovy',($i+1).':00'); //ta +1 je ok kvůli buggy implementaci v DB
  $tpl->parse('upravy.konec');
}

$a=dbQuery('SELECT u.id_uzivatele, u.login_uzivatele, u.jmeno_uzivatele, u.prijmeni_uzivatele 
  FROM uzivatele_hodnoty u
  LEFT JOIN r_uzivatele_zidle z USING(id_uzivatele)
  LEFT JOIN r_prava_zidle p USING(id_zidle)
  WHERE p.id_prava='.$GLOBALS['ID_PRAVO_ORG_AKCI'].'
  GROUP BY u.id_uzivatele
  ORDER BY u.login_uzivatele');
$tpl->assign('organizator','0'); //nejdřív nabídka bez orga
$tpl->assign('organizatorJmeno','(bez organizátora)');
$tpl->parse('upravy.organizator');
while($r=mysql_fetch_assoc($a))
{
  $tpl->assign('sel',$r['id_uzivatele']==$aktivita['organizator']?'selected':'');
  $tpl->assign('organizator',$r['id_uzivatele']);
  $tpl->assign('organizatorJmeno',jmenoNick($r));
  $tpl->parse('upravy.organizator');
}

$tpl->assign(array('sel'=>'','id_typu'=>0,'typ_1p'=>'(bez typu – organizační)'));
$tpl->parse('upravy.typ');
$a=dbQuery('SELECT * FROM akce_typy');
while($r=mysql_fetch_assoc($a))
{
  $tpl->assign('sel',$r['id_typu']==$aktivita['typ']?'selected':'');
  $tpl->assign($r);
  $tpl->parse('upravy.typ');
}

$a=$aktivita?new Aktivita($aktivita):null;

$tpl->assign('urlObrazku',$a?$a->obrazek():'');
$tpl->assign($aktivita);

$tpl->parse('upravy');
$tpl->out('upravy');

?>
