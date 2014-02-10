<?php

/**
 * Fuknce vracející seznamy. Hack pro Impérium a rok 2013. Do budoucna zpracovat
 * slušně v třídě Aktivita
 */
function seznamy($rodina)
{
  $o=dbQueryS('
    SELECT
      a.*,
      o.login_uzivatele as org, 
      GROUP_CONCAT(DISTINCT u.login_uzivatele ORDER BY l.cas SEPARATOR "<br>") as hraci
    FROM akce_seznam a
    JOIN akce_prihlaseni p ON(p.id_akce=a.id_akce) -- nevypisovat běhy s 0 lidmi
    LEFT JOIN uzivatele_hodnoty u ON(u.id_uzivatele=p.id_uzivatele)
    LEFT JOIN uzivatele_hodnoty o ON(o.id_uzivatele=a.organizator)
    LEFT JOIN akce_prihlaseni_log l ON(p.id_uzivatele=l.id_uzivatele AND p.id_akce=l.id_akce)
    WHERE a.id_akce=$0 AND a.id_akce>0 AND a.stav=1 -- >0 kvůli situaci kdy není víc instancí "impéria" ještě
    GROUP BY a.id_akce 
  ',array($rodina));
  $out='';
  while($r=mysql_fetch_assoc($o))
  {
    $a=new Aktivita($r);
    $out.='<div class="seznam"><b>';
    $out.=$r['org'].'<br>';
    $out.=$a->zacatek()->format('l G:00').'<br>';
    $out.='</b>';
    $out.=$r['hraci'];
    $out.='</div>';
  }
  
  return $out;
}  

UzivatelskaAktivita::postPrihlasOdhlas($u);

$xtpl=new XTemplate('./templates/aktivita.xtpl');
$a=$aktivita;
$aktivita=new Aktivita($aktivita); // zhybridování DB a objektového přístupu :/

//Výběr dalších instancí, pokud jsou
$orgoveIds=array();
if($a['patri_pod'])
{
  $aa=UzivatelskaAktivita::nactiSkupinu('rok='.ROK_AKTUALNI.' AND (stav=1 OR stav=2 OR stav=4) AND patri_pod='.$a['patri_pod'],$u,'den, zacatek');
  $prihlasenVSkupine=false;
  while($r=mysql_fetch_assoc($aa))
  {
    $skupina[]=$aaa=new UzivatelskaAktivita($r,$u);
    if($aaa->prihlasen())
      $prihlasenVSkupine=true;
    $orgoveIds[$aaa->orgId()]=true;
  }
  foreach($skupina as $aaa)
  {
    $xtpl->assign('cas', count(array_keys($orgoveIds))>1 ? 
      $aaa->denCas().' ('.$aaa->orgJmeno().') ' :
      $aaa->denCas() 
    );
    if($aaa->prihlasen() || !$prihlasenVSkupine) //v skupině aktivit je možné max. jedno přihlášení, v tom případě netiskneme další přihlašovátka
      $xtpl->assign('prihlasovatko',$aaa->prihlasovatko($u));
    else
      $xtpl->assign('prihlasovatko','');
    $xtpl->assign('obsazenost',$aaa->obsazenostHtml());
    $xtpl->assign('tridy',$aaa->prihlasovatelna()?'':'neprihlasovatelna');
    $xtpl->parse('aktivita.instance');
  }
}
else
{
  $aa=UzivatelskaAktivita::nactiSkupinu('a.id_akce='.$a['id_akce'],$u);
  $r=mysql_fetch_assoc($aa);
  $aaa=new UzivatelskaAktivita($r,$u);
  $xtpl->assign('cas',datum2($r));
  $xtpl->assign('prihlasovatko',$aaa->prihlasovatko());
  $xtpl->assign('obsazenost',$aaa->obsazenostHtml());
  $xtpl->assign('tridy',$aaa->prihlasovatelna()?'':'neprihlasovatelna');
  $xtpl->parse('aktivita.instance');
}

//výběr organizátora / organizátorů a dalších jejich aktivit, pokud jsou
//zatím víc orgů jen provizorně, aktivity se píšou od prvního
$org=null;
$orgove=array();
if($a['organizator'])
{
  //Výběr dalších aktivit organizátora
  $dalsiAktivityOrga=array();
  $aa=dbQuery('SELECT nazev_akce, url_akce, url_typu, IF(patri_pod,-patri_pod,id_akce) as gid 
    FROM akce_seznam a
    JOIN akce_typy t ON(a.typ=t.id_typu)
    WHERE organizator='.$a['organizator'].'
    AND rok='.ROK_AKTUALNI.'
    AND (stav=1 OR stav=4)
    AND url_akce!="" -- instance bez url se vyskytnou tam, kde je org pouze orgem instance a není orgem "vedoucí" aktivity
    AND NOT(id_akce='.$a['id_akce'].' OR (patri_pod AND patri_pod='.$a['patri_pod'].'))
    GROUP BY gid');
  while($r=mysql_fetch_assoc($aa))
    $dalsiAktivityOrga[]='<a href="'.$r['url_typu'].'/'.$r['url_akce'].'">'.
    $r['nazev_akce'].'</a>';
  if($dalsiAktivityOrga)
    $xtpl->insert_loop('aktivita.dalsiAktivityOrga','dalsiAktivityOrga',implode(
    ', ',$dalsiAktivityOrga));
    
  //Výběr nicku organizátora/ů
  if($orgoveIds) //víc orgů
  {
    $orgSql=dbQuery('SELECT * FROM uzivatele_hodnoty 
      WHERE id_uzivatele='.implode(' OR id_uzivatele=',array_keys($orgoveIds)));
    while($r=mysql_fetch_assoc($orgSql))
      $orgove[]=new Uzivatel($r);
  }
  else
    $org=new Uzivatel(dbOneLine('SELECT * FROM uzivatele_hodnoty 
      WHERE id_uzivatele='.$a['organizator']));
}

if($org || $orgove)
{
  if($org)
    $organizator=$org->jmenoNick();
  else
  {
    foreach($orgove as $orgJeden)
      $organizator[]=$orgJeden->jmenoNick();
    $organizator=implode(', ',$organizator);
  }
  $xtpl->assign(array(
    'titul_orga'=>ucfirst($a['titul_orga']),
    'organizator'=>$organizator,
  ));
  $xtpl->parse('aktivita.org');
}
$xtpl->assign($a);
$popis= strpos($aktivita->popis(),'{seznamy}')!==false ? str_replace('{seznamy}',seznamy($a['id_akce']),$aktivita->popis()) : $aktivita->popis() ; // TODO magická konstanta pro aktivitu Impérium (legendy klubu dobrodruhů) 
$xtpl->assign(array(
  'urlObrazku'=>$aktivita->obrazek(),
  'popis'=>$popis,
));
$titulek->aktivita($aktivita); //glob. titulek stránky

// výpočet a zobrazení ceny
if(CENY_VIDITELNE)
{
  $do=new DateTime(SLEVA_DO);
  $xtpl->assign('cena',$aktivita->cena($u));
  $xtpl->assign('stdCena',$aktivita->cena(null));
  $xtpl->assign('zakladniCena',$aktivita->cenaZaklad().'&thinsp;Kč');
  $xtpl->assign('rozhodneDatum',$do->format('j.n.'));
  if($aktivita->bezSlevy())         $xtpl->parse('aktivita.fixniCena');
  elseif($u && $u->gcPrihlasen())   $xtpl->parse('aktivita.mojeCena');
  else                              $xtpl->parse('aktivita.cena');
}

$xtpl->parse('aktivita');
$obsah=$xtpl->text('aktivita');

?>
