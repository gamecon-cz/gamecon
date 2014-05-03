<?php

$VLASTNI_VYSTUP = true; //předáváno volajícímu scriptu
header('content-type: text/xml'); //nastavení typu výstupu, bez tohoto by nevyšel xml výpis

$rss = new SimpleXMLElement('<rss version="2.0"></rss>');
$ch = $rss->addChild('channel');
$ch->title = 'test';
$ch->link = 'http://gamecon.cz/forum';
$ch->description = 'Novinky z GameCon fóra';
$ch->language = 'cz';
$ch->pubDate = gmdate('D, d M Y H:i:s').' GMT';
$ch->lastBuildDate = $ch->pubDate;

$o = dbQuery('
  SELECT
    obsah,
    jmeno_podsekce,
    DATE_FORMAT(FROM_UNIXTIME(datum), "%a, %d %M %Y %H:%i:%s +0100") as casf,
    IF(isnull(u.login_uzivatele),c.jmeno_neregistrovany,u.login_uzivatele) as uzivatel,
    jmeno_sekce_mini as sekce, jmeno_podsekce_mini as podsekce
  FROM forum_clanky c
  JOIN forum_podsekce p ON(c.patri=p.id_podsekce)
  JOIN forum_sekce s ON(p.patri=s.id_sekce)
  LEFT JOIN uzivatele_hodnoty u ON(c.uzivatel=u.id_uzivatele)
  -- todo tajná fóra nezahrnout
  ORDER BY datum DESC
  LIMIT 10
');
while($r = mysql_fetch_assoc($o)) {
  $item = $ch->addChild('item');
  $item->title = $r['jmeno_podsekce'];
  $item->link = 'http://gamecon.cz/forum/'.$r['sekce'].'/'.$r['podsekce'];
  $item->pubDate = $r['casf'];
  $item->description = substr(strip_tags($r['obsah']),0,1024);
}

echo $rss->asXml();
