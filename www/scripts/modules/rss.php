<?php
$VLASTNI_VYSTUP=true; //předáváno volajícímu scriptu
header('content-type: text/xml'); 
  //nastavení typu výstupu, bez tohoto by nevyšel xml výpis
$ren= gmdate('D, d M Y H:i:s').' GMT'; 
  //datum poslední úpravy <<právě teď a je shodné s datem publikace 
echo '<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0">  
  <channel>
    <title>Novinky gamecon.cz</title>
    <link>http://gamecon.cz/</link>
    <description>Novinky o GameConu</description>
    <language>cs</language>
    <pubDate>'.$ren.'</pubDate>
    <lastBuildDate>'.$ren.'</lastBuildDate>
    '."\n\n"; ?><?php //debilni pspad

$o=dbQuery('SELECT obsah, DATE_FORMAT(publikovano, "%a, %d %M %Y %H:%i:%s +0100") as casf 
  FROM novinky_obsah 
  WHERE stav="Y" 
  ORDER BY publikovano DESC 
  LIMIT 10');

while ($radek = mysql_fetch_array($o))
{ 
  $match=null;
  preg_match('@<h2>([^<]+)</h2>[^<]+<h3>([^<]+)</h3>@m',$radek['obsah'],$match);
  $titulek=$match[2];
  ?>
  <item>
    <title><?php echo $titulek ?></title>
    <link>http://gamecon.cz/novinky</link> 
    <pubDate><?php echo $radek['casf'] ?></pubDate>
    <description><?php echo substr(strip_tags($radek['obsah']),0,1024) ?></description>
  </item>
  <?php 
} ?>
  </channel>
</rss>