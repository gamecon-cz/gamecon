<?php

//ošetření vstupů z url
if(!$url->cast(1))
  throw new Exception('Nezadán typ archivu');
$url2=explode('-',$url->cast(1));
if(count($url2)!=2)
  0; //todo chyba nesprávný počet parametrů

//možno načítat z db @fixme
$orgTitul='';
$aktivitaTyp=4; //default hodnota
switch($url2[0])
{
  case 'deskovky': 
    $aktivitaTyp=1;
    $nazevKategorie='turnajů v deskových hrách';
    //$orgTitul='organizátor'; //deskovky jsou anonymní
    break;
  case 'larpy': 
    $aktivitaTyp=2;
    $nazevKategorie='larpů';
    $orgTitul='organizátor';
    break;
  case 'prednasky': 
    $aktivitaTyp=3;
    $nazevKategorie='přednášek';
    $orgTitul='přednášející';
    break;
  case 'rpg': 
    $aktivitaTyp=4;
    $nazevKategorie='RPG';
    $orgTitul='vypravěč';
    break;
  //5 - workshop (ojedinely)
  case 'wargaming': 
    $aktivitaTyp=6;
    $nazevKategorie='wargamingu';
    $orgTitul='warganizátor';
    break;
  //7 - bonusy
  default:
    0; //todo chyba neexistujici aktivita
}
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
  LEFT JOIN uzivatele_hodnoty u ON(u.id_uzivatele=a.organizator)
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
    $xtpl->assign('jmeno',jmenoNick($radek));
    $xtpl->assign('titul',$orgTitul);
    $xtpl->parse('archiv.polozka.organizator');
  }
  $radek['popis']=encHtml2($popis);
  //@todo obrázek pořešit později, protože archivy nemají url akcí řádně uvedeno
  //$radek['obrazek']='/system_styly/side/'.$radek['obrazek'];
  $xtpl->assign($radek);
  if($radek['url'])
  {
    $xtpl->assign('urlObrazku','/files/systemove/aktivity/'.$radek['url'].'.jpg');
    $xtpl->parse('archiv.polozka.obrazek');
  }
  $xtpl->parse('archiv.polozka');
}

$xtpl->parse('archiv');
$xtpl->out('archiv');

?>
