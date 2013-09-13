<?php
$VLASTNI_VYSTUP=true; //předáváno volajícímu scriptu
header('content-type: text/xml'); 
  //nastavení typu výstupu, bez tohoto by nevyšel xml výpis
$ren= gmdate('D, d M Y H:i:s').' GMT'; 
  //datum poslední úpravy <<právě teď a je shodné s datem publikace 
echo '<?xml version="1.0" encoding="utf-8"?'.'>
<rss version="2.0">  
  <channel>
    <title>Novinky fóra gamecon.cz</title>
    <link>http://gamecon.cz/forum</link>
    <description>Novinky z GameCon fóra</description>
    <language>cs</language>
    <pubDate>'.$ren.'</pubDate>
    <lastBuildDate>'.$ren.'</lastBuildDate>
    '."\n\n";

$o=dbQuery('SELECT 
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
  LIMIT 10');

while($r=mysql_fetch_assoc($o))
{ 
  ?>
  <item>
    <title><?php echo $r['jmeno_podsekce'] ?></title>
    <link>http://gamecon.cz/forum/<?php echo $r['sekce'].'/'.$r['podsekce'] ?></link> 
    <pubDate><?php echo $r['casf'] ?></pubDate>
    <description><?php echo substr(strip_tags($r['obsah']),0,1024) ?></description>
  </item>
  <?php 
} ?>
  </channel>
</rss>