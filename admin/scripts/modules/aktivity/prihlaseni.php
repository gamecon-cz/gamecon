<?php

/** 
 * Stránka pro přehled všech přihlášených na aktivity
 *
 * nazev: Seznam přihlášených
 * pravo: 102
 */
 
$xtpl2=new XTemplate('prihlaseni.xtpl');

$xtpl2->parse('prihlaseni.vyber');
if(!isset($_GET['typ']))
{
  $xtpl2->parse('prihlaseni');
  $xtpl2->out('prihlaseni');
  return;
}

$odpoved=dbQuery('
  SELECT a.nazev_akce as nazevAktivity, a.id_akce as id, (a.kapacita+a.kapacita_m+a.kapacita_f) as kapacita, a.den, a.zacatek,
    a.konec, u.login_uzivatele as nick, u.jmeno_uzivatele as jmeno, 
    u.prijmeni_uzivatele as prijmeni, u.email1_uzivatele as mail, u.telefon_uzivatele as telefon
  FROM akce_seznam a
  LEFT JOIN akce_prihlaseni p ON (a.id_akce=p.id_akce)
  LEFT JOIN uzivatele_hodnoty u ON (p.id_uzivatele=u.id_uzivatele)
  WHERE a.rok='.ROK.'
  AND a.den>0
  AND (a.stav=1 || a.stav=2)
  '.(isset($_GET['typ'])?'AND a.typ='.get('typ'):'').'
  ORDER BY a.den, a.zacatek, a.nazev_akce, a.id_akce, p.id_uzivatele');

$totoPrihlaseni=mysql_fetch_array($odpoved);
$dalsiPrihlaseni=mysql_fetch_array($odpoved);
$obsazenost=0;
$odd=0;
$maily=array();
while($totoPrihlaseni)
{
  $xtpl2->assign($totoPrihlaseni);
  if($totoPrihlaseni['nick'])
  {
    $xtpl2->assign('odd',$odd?$odd='':$odd='odd');
    $xtpl2->parse('prihlaseni.aktivita.lide.clovek');
    $maily[]=$totoPrihlaseni['mail'];
    $obsazenost++;
  }
  if($totoPrihlaseni['id']!=$dalsiPrihlaseni['id'])
  {
    $xtpl2->assign('maily',implode('; ',$maily));
    $xtpl2->assign('cas',datum2($totoPrihlaseni));
    $xtpl2->assign('obsazenost',$obsazenost.
      ($totoPrihlaseni['kapacita']?'/'.$totoPrihlaseni['kapacita']:''));
    if($obsazenost)
      $xtpl2->parse('prihlaseni.aktivita.lide');
    $xtpl2->parse('prihlaseni.aktivita');
    $obsazenost=0;
    $odd=0;
    $maily=array();
  }  
  $totoPrihlaseni=$dalsiPrihlaseni;
  $dalsiPrihlaseni=mysql_fetch_array($odpoved);
}

$xtpl2->parse('prihlaseni');
$xtpl2->out('prihlaseni');

?>
