<style>
td {border: solid 1px; padding: 5px;}
table {text-align: center; border-collapse: collapse;}
</style>

<?php
require_once('sdilene-hlavicky.hhp');

echo '<table>
<tr><td>DEN</td><td>ČAS</td><td>PROŠLO INFOPULTEM</td></tr>';

for ($i = 1; $i < 5; $i++) {
  for ($j = 7; $j < 24; $j++) {
    // stav = -1502 (GC 2015 přítomen)
    $o = dbOneCol("
      SELECT COUNT(*)
      FROM r_uzivatele_zidle
      WHERE id_zidle=-1502
      AND posazen between '2015-07-".(15+$i)." ".$j.":00:00' AND '2015-07-".(15+$i)." ".($j+1).":00:00'
    ");
    echo "<tr><td>$i</td><td>$j</td><td>$o</td></tr>";
  }
}

echo '</table>';
