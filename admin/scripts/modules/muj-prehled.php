<?php

/** 
 * Úvodní karta organizátora s přehledem jeho aktivit
 * 
 * Kód zkopírován z aktivity/prihlaseni.php. Ideálně ve chvíli osvícení nějak
 * sjednotit. UPDATE 22.12.12: Rozšířeno nezávisle na aktivity/prihlaseni.php o
 * čas přihlášky uživatele. V případě problému nasadit index na 
 * akce_prihlaseni_log.id_akce ovšem to zpomalí přihlašování na aktivity mírně.    
 *
 * nazev: Moje aktivity
 * pravo: 4
 */

if(Aktivita::editorZpracuj())
  back();

$tpl=new XTemplate('muj-prehled.xtpl');

$odpoved=dbQuery('
  SELECT a.nazev_akce as nazevAktivity, a.id_akce as id, (a.kapacita+a.kapacita_m+a.kapacita_f) as kapacita, a.den, a.zacatek,
    a.id_akce, a.patri_pod, a.url_akce, a.popis,  -- dodatečná pole kvůli editoru
    a.konec, u.login_uzivatele as nick, u.jmeno_uzivatele as jmeno, 
    u.prijmeni_uzivatele as prijmeni, u.email1_uzivatele as mail, u.telefon_uzivatele as telefon,
    DATE_FORMAT(MAX(l.cas),"%e.%c.") as cas
  FROM akce_seznam a
  LEFT JOIN akce_prihlaseni p ON (a.id_akce=p.id_akce)
  LEFT JOIN uzivatele_hodnoty u ON (p.id_uzivatele=u.id_uzivatele)
  LEFT JOIN akce_prihlaseni_log l ON (a.id_akce=l.id_akce AND u.id_uzivatele=l.id_uzivatele)
  WHERE a.rok='.ROK.'
  AND a.den>0
  AND (a.stav=1 OR a.stav=2 OR a.stav=4)
  AND a.organizator='.$u->id().'
  GROUP BY a.id_akce, u.id_uzivatele
  ORDER BY a.den, a.zacatek, a.nazev_akce, a.id_akce, l.cas DESC, p.id_uzivatele');

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
    if(!empty($totoPrihlaseni['url_akce']))
    {
      $a=new Aktivita($totoPrihlaseni);
      $tpl->assign('editor',$a->editorVypravec());
      $tpl->parse('prehled.aktivita.editor');
    }
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
