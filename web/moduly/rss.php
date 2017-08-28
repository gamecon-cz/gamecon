<?php
$this->bezStranky(true);
header('content-type: text/xml'); //nastavení typu výstupu, bez tohoto by nevyšel xml výpis
$novinky = Novinka::zNejnovejsich(0, 10);
$ren = gmdate('D, d M Y H:i:s').' GMT'; //datum poslední úpravy <<právě teď a je shodné s datem publikace
?>
<?='<?xml version="1.0" encoding="utf-8"?>'/* bugfix pro verze php které mají problém s otvírací xml závorkou */?>
<rss version="2.0">
  <channel>
    <title>Novinky gamecon.cz</title>
    <link><?=URL_WEBU?></link>
    <description>Novinky o GameConu</description>
    <language>cs</language>
    <pubDate><?=$ren?></pubDate>
    <lastBuildDate><?=$ren?></lastBuildDate>
    <?php foreach($novinky as $n) { ?>
      <item>
        <title><?=htmlspecialchars($n->nazev())?></title>
        <link><?=URL_WEBU?>/novinky</link>
        <pubDate><?=$n->vydat()->format(DATE_RSS)?></pubDate>
        <description><?=htmlspecialchars($n->nahled(250))?></description>
      </item>
    <?php } ?>
  </channel>
</rss>
