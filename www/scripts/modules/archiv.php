<?php

//ošetření vstupů z url
if(!$url->cast(1))
  throw new Exception('Nezadán typ archivu');
$url2[0]=strrbefore($url->cast(1), '-');
$url2[1]=strrafter($url->cast(1), '-');

$typ = mysql_fetch_assoc(dbQueryS('SELECT * FROM akce_typy WHERE url_typu_mn = $0', array($url2[0])));
$aktivitaTyp = $typ['id_typu'];
$nazevKategorie = $typ['typ_2pmn'];
$orgTitul = $typ['titul_orga']; 

if(intval($url2[1]) >= ARCHIV_OD && intval($url2[1]) < ROK_AKTUALNI)
  $rok=intval($url2[1]);
else
  throw new Exception('Rok archivu mimo rozsah.');
if(!isset($nazevKategorie))
  throw new Exception('Neplatná kategorie archivu.');

//hlavní kód
$xtpl=new XTemplate($ROOT_DIR.'/templates/archiv.xtpl');
$xtpl->assign('nadpis','Archiv '.$nazevKategorie.' na GameConu '.$rok); //todo

$odpoved=dbQuery('SELECT a.nazev_akce, a.popis, MAX(a.url_akce) as url,
    u.jmeno_uzivatele, u.prijmeni_uzivatele,
    u.login_uzivatele
  FROM akce_seznam a
  LEFT JOIN akce_organizatori ao USING(id_akce)
  LEFT JOIN uzivatele_hodnoty u USING(id_uzivatele)
  WHERE a.rok='.$rok.'
  AND a.typ='.$aktivitaTyp.'
  AND a.stav!=0
  GROUP BY a.nazev_akce
  ORDER BY a.nazev_akce');
while($radek=mysql_fetch_assoc($odpoved))
{
  $popis=$radek['popis'];
  if($orgTitul && $radek['login_uzivatele'])
  { //aktivita má mít uvedeného organizátora
    $xtpl->assign('jmeno', Uzivatel::jmenoNickZjisti($radek));
    $xtpl->assign('titul',$orgTitul);
    $xtpl->parse('archiv.polozka.organizator');
  }
  $radek['popis']=encHtml2($popis);
  //TODO obrázek pořešit později, protože archivy nemají url akcí řádně uvedeno
  //$radek['obrazek']='/system_styly/side/'.$radek['obrazek'];
  $xtpl->assign($radek);
  if($radek['url'])
  {
    $xtpl->assign('urlObrazku','files/systemove/aktivity/'.$radek['url'].'.jpg');
    $xtpl->parse('archiv.polozka.obrazek');
  }
  $xtpl->parse('archiv.polozka');
}

$xtpl->parse('archiv');
$xtpl->out('archiv');

?>
