<?php

UzivatelskaAktivita::postPrihlasOdhlas($u);

$xtpl=new XTemplate('./templates/aktivita.xtpl');
$a=$aktivita;

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
  }
  foreach($skupina as $aaa)
  {
    $xtpl->assign('cas',$aaa->denCas());
    if($aaa->prihlasen() || !$prihlasenVSkupine) //v skupině aktivit je možné max. jedno přihlášení, v tom případě netiskneme další přihlašovátka
      $xtpl->assign('prihlasovatko',$aaa->prihlasovatko($u));
    else
      $xtpl->assign('prihlasovatko','');
    $xtpl->assign('obsazenost',$aaa->obsazenostHtml());
    $xtpl->parse('aktivita.instance');
    $orgoveIds[]=$aaa->orgId();
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
    $dalsiAktivityOrga[]='<a href="/'.$r['url_typu'].'/'.$r['url_akce'].'">'.
    $r['nazev_akce'].'</a>';
  if($dalsiAktivityOrga)
    $xtpl->insert_loop('aktivita.dalsiAktivityOrga','dalsiAktivityOrga',implode(
    ', ',$dalsiAktivityOrga));
    
  //Výběr nicku organizátora/ů
  if($orgoveIds) //víc orgů
  {
    $orgSql=dbQuery('SELECT * FROM uzivatele_hodnoty 
      WHERE id_uzivatele='.implode(' OR id_uzivatele=',$orgoveIds));
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
$xtpl->assign(array(
  'urlObrazku'=>'/files/systemove/aktivity/'.$a['url_akce'].'.jpg',
  'popis'=>encHtml2($a['popis']),
));
$titulek->aktivita(new Aktivita($a)); //glob. titulek stránky

$xtpl->parse('aktivita');
$obsah=$xtpl->text('aktivita');

?>
