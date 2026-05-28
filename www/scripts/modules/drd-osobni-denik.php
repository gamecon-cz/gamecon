<?

require_once('drd-konstanty.hhp');

header('Content-Type: text/html; charset=utf-8');

if(get('uid'))
  $uid=(int)get('uid');
elseif(ma_pravo($_SESSION["id_uzivatele"],$GLOBALS['ID_PRAVO_DRD']))
  $uid=$_SESSION["id_uzivatele"];
else
  exit('Deník je přístupný pouze účastníkům DrD.');

$postava=dbOneLine('
  SELECT p.jmeno, p.rasa, p.povolani, s.schopnosti, i.poznamka, v.vybaveni
  FROM drd_postava p
  LEFT JOIN postavy_schopnosti s USING(id_uzivatele,rok)
  LEFT JOIN postavy_poznamka i USING(id_uzivatele,rok)
  LEFT JOIN postavy_vybaveni v USING(id_uzivatele,rok)
  WHERE p.rok='.$ROK_AKTUALNI.'
  AND p.id_uzivatele='.$uid);

$zbraneBlizko=dbQuery('
  SELECT *
  FROM postavy_zbrane_f2f
  WHERE rok='.$ROK_AKTUALNI.'
  AND id_uzivatele='.$uid.'
  AND nazev!=""');

$zbraneDalka=dbQuery('
  SELECT *
  FROM postavy_zbrane_str
  WHERE rok='.$ROK_AKTUALNI.'
  AND id_uzivatele='.$uid.'
  AND nazev!=""');

$xtpl=new XTemplate($ROOT_DIR.'/templates/drd-osobni-denik.xtpl');

$xtpl->assign($postava);
$xtpl->assign('rasa',$DRD_RASA[$postava['rasa']]);
$xtpl->assign('povolani',$DRD_POVOLANI[$postava['povolani']]);
$xtpl->assign('zivoty',$postavy[$postava['rasa']][$postava['povolani']][6]);
$xtpl->assign('sila',$postavy[$postava['rasa']][$postava['povolani']][1]);
$xtpl->assign('obratnost',$postavy[$postava['rasa']][$postava['povolani']][2]);
$xtpl->assign('odolnost',$postavy[$postava['rasa']][$postava['povolani']][3]);
$xtpl->assign('inteligence',$postavy[$postava['rasa']][$postava['povolani']][4]);
$xtpl->assign('charisma',$postavy[$postava['rasa']][$postava['povolani']][5]);
$xtpl->assign('velikost',$DRD_VELIKOST[$postava['rasa']]);
$xtpl->assign('vybaveni',strtr($postava['vybaveni'],array("\n"=>'<br />')));
$xtpl->assign('schopnosti',strtr($postava['schopnosti'],array("\n"=>'<br />')));
$xtpl->assign('poznamka',strtr($postava['poznamka'],array("\n"=>'<br />')));

while($zbran=mysql_fetch_array($zbraneBlizko))
{
  $xtpl->assign($zbran);
  $xtpl->parse('denik.zbranBlizko');
}

while($zbran=mysql_fetch_array($zbraneDalka))
{
  $xtpl->assign($zbran);
  $xtpl->parse('denik.zbranDalka');
}

$xtpl->parse('denik');
$xtpl->out('denik');

exit();
  
?>
