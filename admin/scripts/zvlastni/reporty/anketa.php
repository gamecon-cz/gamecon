<?php

require __DIR__ . '/sdilene-hlavicky.php';

//$MULTI_CHECK=3; //počet duplicitních sloupců vyhrazených pro otázky s checboxy //zatím neimplementováno

if(!isset($_GET['id']))
  exit('Není nastaveno id ankety.');

$id=$_GET['id'];

$o=dbQuery('
  SELECT id_otazky, typ, text -- TODO vyspořádat se s "check"
  FROM ankety_otazky
  WHERE id_ankety='.$id.'
  ORDER BY id_otazky');

$otazkyId=[];
$otazky=[];
while($r=mysqli_fetch_assoc($o))
{
  $otazkyId[]=$r['id_otazky'];
  $otazky[]=$r['text'];
  if($r['typ']=='check')
  { //pracujeme s checkboxy, více možností
    //for($i=0;$i<$MULTI_CHECK-1;$i++) $otazkyId[]=$r['id_otazky'];
    $otazkyId[]=$r['id_otazky'];
    $otazky[]=$r['text'];
    $otazkyId[]=$r['id_otazky'];
    $otazky[]=$r['text'];
    $otazkyId[]=$r['id_otazky'];
    $otazky[]=$r['text'];
    $otazkyId[]=$r['id_otazky'];
    $otazky[]=$r['text'];
    $otazkyId[]=$r['id_otazky'];
    $otazky[]=$r['text'];
    $otazkyId[]=$r['id_otazky'];
    $otazky[]=$r['text'];
    $otazkyId[]=$r['id_otazky'];
    $otazky[]=$r['text'];
    $otazkyId[]=$r['id_otazky'];
    $otazky[]=$r['text'];
    $otazkyId[]=$r['id_otazky'];
    $otazky[]=$r['text'];
  }
}

header('Content-type: application/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$NAZEV_SKRIPTU.'.csv"');

echo(chr(0xEF).chr(0xBB).chr(0xBF)); //BOM bajty pro nastavení UTF-8 ve výsledném souboru

$out=fopen('php://output','w'); //získáme filedescriptor výstupu stránky pro použití v fputcsv

fputcsv($out,$otazky,$CSV_SEP); //tisk hlavičkových buněk

$o=dbQuery('
  SELECT h.id_otazky, h.id_uzivatele, o.text, h.odpoved
  FROM ankety_uzivatele_odpovedi h
  LEFT JOIN ankety_odpovedi o ON(h.id_odpovedi=o.id_odpovedi)
  WHERE h.id_ankety='.$id.'
  ORDER BY h.id_uzivatele, h.id_otazky');

$r=mysqli_fetch_assoc($o);
$uzivatel=0;
while(1)
{
  foreach($otazkyId as $idOtazky)
  {
    if($uzivatel!=$r['id_uzivatele'] && $uzivatel!=0)
    { //narazili jsme na odpovědi dalšího uživatele
      fputcsv($out,$odpovedi,$CSV_SEP);
      $odpovedi=null;
      if(!$r) //aktuální řádek už je neplatný, vyskočíme z vnějšího while
        break 2;
    }
    $uzivatel=$r['id_uzivatele'];

    if($idOtazky<$r['id_otazky'])
    { //aktuální otázka není zodpovězena => dáváme prázdné pole a iterujeme dál
      $odpovedi[]='';
      continue;
    }
    else
    { //aktuální otázka je zodpovězena
      if($r['id_otazky']==$idOtazky && $r['id_uzivatele']==$uzivatel)
      {
        //PŮVODNĚ: přidáváme sloupce, dokud je to stejná otázka a stejný uživatel (to je pokud se použije místo if while, není komplet implementováno)
        //NYNÍ: potenciálně buggy. Mělo by to fungovat tak, že do $otazkyId se dají multiodpovědi víckrát.
        $odpovedi[]= $r['odpoved']=='#Y#'?$r['text']:$r['odpoved'];
        $r=mysqli_fetch_assoc($o);
        //TODO: rozbije se to, pokud člověk vyplní víc než limit u checkboxů (pořešit situaci $idOtazky>$r['id_otazky'] asi)
      }
    }
  }
}

//var_dump($odpovedi);


fclose($out);

?>
