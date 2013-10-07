<?php

require_once('sdilene-hlavicky.hhp');

// seznam roku vezmeme z aktivit
$sql_roky = dbQuery("SELECT MIN(rok) FROM akce_seznam GROUP BY rok ORDER BY rok");

$sloupce_roky = array();
$query_roky = array();

// pro kazdy rok vygenerujeme sloupec
while(list($rok) = mysql_fetch_row($sql_roky)){
	array_push($sloupce_roky, $rok);
	array_push(
		$query_roky,
		"
    IF(
      MAX(z.id_zidle IN (2, 4) AND (z.posazen = '0000-00-00 00:00:00' OR z.posazen <= '$rok-06-01 00:00:00')),
      'organizator',
      IF(
        MAX(z.id_zidle = -".intval(substr($rok, 1))."02),
        IF(
          MAX(z.id_zidle IN (6, 9) AND (z.posazen = '0000-00-00 00:00:00' OR z.posazen <= '$rok-06-01 00:00:00')),
          'vypravec',
          'pritomen'
        ),
        IF(
          MAX(z.id_zidle = -".intval(substr($rok, 1))."01),
          'nedorazil',
          'neprihlasen'
        )
      )
    ) rok$rok"
	);
}

$query = dbQuery("
  SELECT
    u.id_uzivatele,
	u.pohlavi,
    ".implode(",", $query_roky)."
  FROM uzivatele_hodnoty u
  LEFT JOIN r_uzivatele_zidle z
    ON u.id_uzivatele = z.id_uzivatele
  LEFT JOIN akce_seznam a
    ON a.organizator = u.id_uzivatele
  GROUP BY u.id_uzivatele;
  ");
  
if(mysql_num_rows($query) <= 0) {
  exit('V databázi asi nejsou žádná data (ale spíše se stala chyba někde jinde).');
}

/*
// DEBUG verze
// v pripade, ze nechces CSV ale tabulku, pridej lomitko o dva radky vyse
echo "<table>\n";
echo "<tr>\n";
echo "  <td>ID</td>\n";
echo "  <td>Pohlaví</td>\n";
foreach($sloupce_roky as $val){
  echo "  <td>Rok $val</td>\n";
}
echo "</tr>\n";

while($data = mysql_fetch_row($query)){
	echo "<tr>\n";
	foreach($data as $sloupec){
		echo "  <td>$sloupec</td>\n";
	}
	echo "</tr>\n";
}
echo "</table>\n";

exit('<br />DEBUG END̈́');
//*/

header('Content-type: application/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$NAZEV_SKRIPTU.'.csv"');
echo(chr(0xEF).chr(0xBB).chr(0xBF)); //BOM bajty pro nastavení UTF-8 ve výsledném souboru

$out=fopen('php://output','w'); //získáme filedescriptor výstupu stránky pro použití v fputcsv

// header
$sloupce = array("id_uzivatele", "pohlavi");
foreach($sloupce_roky as $key=>$value){
	array_push($sloupce, "rok$value");
}
fputcsv($out, $sloupce, $CSV_SEP);

// data
while($data = mysql_fetch_row($query)){
	fputcsv($out, $data, $CSV_SEP);
}

fclose($out);

