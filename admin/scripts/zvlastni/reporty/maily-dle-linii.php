<?php

require_once('sdilene-hlavicky.php');

function hlavicka() {
  $o = dbQuery('
  SELECT typ_1p
  FROM akce_typy
  ORDER BY id_typu
  ');

  $hlavicka[0] = "e-mail";
  $hlavicka[1] = "pozice";
  while($r=mysqli_fetch_assoc($o)) {
    array_push($hlavicka, $r['typ_1p']);
  }
  return $hlavicka;
}

function obsah($tabulka) {
  if($tabulka == 'akce_prihlaseni') {
    $doplnek = ' AND '.$tabulka.'.id_stavu_prihlaseni IN (1,2) ';
    $pozice = 'Účastník';
  } elseif($tabulka == 'akce_organizatori') {
    $pozice = 'Vypravěč';
    $doplnek = '';
  }

  $o = dbQuery('
  SELECT u.email1_uzivatele AS "e-mail", GROUP_CONCAT(DISTINCT a.typ ORDER BY a.typ ASC SEPARATOR ", ") AS typ
  FROM '.$tabulka.'
  LEFT JOIN uzivatele_hodnoty u
  ON '.$tabulka.'.id_uzivatele = u.id_uzivatele
  LEFT JOIN akce_seznam a
  ON '.$tabulka.'.id_akce = a.id_akce
  WHERE a.rok = '.ROK.$doplnek.'
  GROUP BY u.email1_uzivatele;
  ');

  $obsah = [];
  $radek = [];
  $pocetLinii = count(hlavicka());

  while($r=mysqli_fetch_assoc($o)) {
    $linieHrace = explode(",", $r['typ']);
    $radek[0] = $r['e-mail'];
    $radek[1] = $pozice;
    for($i = 1; $i < $pocetLinii-1 ; $i++ ) { // Iteruj přes všechny linie
      in_array($i, $linieHrace) ? array_push($radek, "1") : array_push($radek, "0"); // Je linie v poli linií, které uživatel hrál?
    }
    array_push($obsah, $radek); // Přidání aktuální řádku
    $radek = array(); // Smaž obsah řádku
  }
  return $obsah;
}

//obsah('akce_organizatori');

header('Content-type: application/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$NAZEV_SKRIPTU.'.csv"');
echo(chr(0xEF).chr(0xBB).chr(0xBF)); //BOM bajty pro nastavení UTF-8 ve výsledném souboru

$out=fopen('php://output','w'); //získáme filedescriptor výstupu stránky pro použití v fputcsv

fputcsv($out,hlavicka(),$CSV_SEP); //tisk hlavičkových buněk
foreach(obsah('akce_prihlaseni') as $r) { // tisk účastníků dle linií
  fputcsv($out,$r,$CSV_SEP);
}
foreach(obsah('akce_organizatori') as $r) { // tisk vypravěčů dle linií
  fputcsv($out,$r,$CSV_SEP);
}

fclose($out);
