<?php

$VLASTNI_VYSTUP=true;

/**
 * Určí a vrátí typ akce do condroid výstupu
 * $a - řádek z db 
 */ 
function condTyp($a)
{
  if($a['typ']==3)
    return 'P';
  elseif($a['typ']==5)
    return 'W';
  else
    return 'G';
}

$o=dbQuery('
  SELECT a.*, u.*, t.typ_1pmn, 
    IF(a.patri_pod,am.popis,a.popis) as popis
  FROM akce_seznam a
  LEFT JOIN uzivatele_hodnoty u ON(a.organizator=u.id_uzivatele)
  LEFT JOIN akce_seznam am ON(am.patri_pod=a.patri_pod AND am.patri_pod AND NOT ISNULL(am.popis))
  JOIN akce_typy t ON(t.id_typu=a.typ)
  WHERE a.rok='.ROK.'
  AND (a.stav=1 || a.stav=2 || a.stav=3 || a.stav=4)
  AND a.den>0
  ORDER BY a.den, a.zacatek
');

$xml=new DomDocument('1.0');
$root=$xml->createElement('annotations');
$root=$xml->appendChild($root);
for($i=0;$r=mysql_fetch_assoc($o);$i++)
{
  $ao=new Aktivita($r);
  $aktivita=$xml->createElement('programme');
  $aktivita->appendChild($xml->createElement('pid',$r['id_akce']));
  $autor=new Uzivatel($r);
  $aktivita->appendChild($n=$xml->createElement('author'));
    $n->appendChild($xml->createCDATASection($autor->jmenoNick()));
  $aktivita->appendChild($n=$xml->createElement('title'));
    $n->appendChild($xml->createCDATASection($r['nazev_akce']));
  $aktivita->appendChild($xml->createElement('type',condTyp($r)));
  $aktivita->appendChild($n=$xml->createElement('program-line'));
    $n->appendChild($xml->createCDATASection(ucfirst($r['typ_1pmn'])));
  if($ao->zacatek())
  {
    $aktivita->appendChild($xml->createElement('start-time',$ao->zacatek()->format(DATE_ISO8601)));
    $aktivita->appendChild($xml->createElement('end-time',$ao->konec()->add(new DateInterval('PT1H'))->format(DATE_ISO8601)));
  }
  if($r['popis'])
  {
    $aktivita->appendChild($n=$xml->createElement('annotation'));
      $n->appendChild($xml->createCDATASection(ucfirst($r['popis'])));
  }
  $root->appendChild($aktivita);
}
$root->setAttribute('count',$i);
$root->setAttribute('last-update',date(DATE_ISO8601));

Header('Content-type: text/xml');
$xml->formatOutput=true;
echo $xml->saveXML();
//echo '<pre>';
//echo htmlentities( $xml->saveXML() );

?>
