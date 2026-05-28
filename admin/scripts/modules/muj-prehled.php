<?php

/** 
 * Úvodní karta organizátora s přehledem jeho aktivit
 * 
 * Kód zkopírován z aktivity/prihlaseni.php. Ideálně ve chvíli osvícení nějak
 * sjednotit.   
 *
 * nazev: Moje aktivity
 * pravo: 4
 */

$tpl=new XTemplate('muj-prehled.xtpl');

$odpoved=dbQuery('
  SELECT a.nazev_akce as nazevAktivity, a.id_akce as id, (a.kapacita+a.kapacita_m+a.kapacita_f) as kapacita, a.den, a.zacatek,
    a.konec, u.login_uzivatele as nick, u.jmeno_uzivatele as jmeno, 
    u.prijmeni_uzivatele as prijmeni, u.email1_uzivatele as mail, u.telefon_uzivatele as telefon
  FROM akce_seznam a
  LEFT JOIN akce_prihlaseni p ON (a.id_akce=p.id_akce)
  LEFT JOIN uzivatele_hodnoty u ON (p.id_uzivatele=u.id_uzivatele)
  WHERE a.rok='.ROK.'
  AND a.den>0
  AND a.stav=1
  AND a.organizator='.$u->id().'
  ORDER BY a.den, a.zacatek, a.nazev_akce, a.id_akce, p.id_uzivatele');

$totoPrihlaseni=mysql_fetch_array($odpoved);
$dalsiPrihlaseni=mysql_fetch_array($odpoved);
$obsazenost=0;
$maily=array();
while($totoPrihlaseni)
{
  $tpl->assign($totoPrihlaseni);
  if($totoPrihlaseni['nick'])
  {
    $tpl->parse('prehled.aktivita.lide.clovek');
    $maily[]=$totoPrihlaseni['mail'];
    $obsazenost++;
  }
  if($totoPrihlaseni['id']!=$dalsiPrihlaseni['id'])
  {
    $tpl->assign('maily',implode('; ',$maily));
    $tpl->assign('cas',datum2($totoPrihlaseni));
    $tpl->assign('obsazenost',$obsazenost.
      ($totoPrihlaseni['kapacita']?'/'.$totoPrihlaseni['kapacita']:''));
    if($obsazenost)
      $tpl->parse('prehled.aktivita.lide');
    $tpl->parse('prehled.aktivita');
    $obsazenost=0;
    $maily=array();
  }  
  $totoPrihlaseni=$dalsiPrihlaseni;
  $dalsiPrihlaseni=mysql_fetch_array($odpoved);
}
if(mysql_num_rows($odpoved)==0)
  $tpl->parse('prehled.zadnaAktivita');

$tpl->parse('prehled');
$tpl->out('prehled');
 
?>
