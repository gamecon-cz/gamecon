<?php

/**
 * Počty her a jednotlivých druhý her pro jednotlivé účastníky
 */

require __DIR__ . '/sdilene-hlavicky.php';

$o = dbQuery('
  SELECT
    p.id_uzivatele,
    count(1) as pocet,
    count(if(a.typ=0,1,null)) as system,
    count(if(a.typ=1,1,null)) as deskovka,
    count(if(a.typ=2,1,null)) as larp,
    count(if(a.typ=3,1,null)) as prednaska,
    count(if(a.typ=4,1,null)) as rpg,
    count(if(a.typ=5,1,null)) as dilna,
    count(if(a.typ=6,1,null)) as wargaming,
    count(if(a.typ=7,1,null)) as bonus
  FROM akce_prihlaseni p
  JOIN akce_seznam a USING(id_akce)
  WHERE a.rok=$1
  GROUP BY p.id_uzivatele', [ROK]);

if (mysqli_num_rows($o) == 0) {
    exit('V tabulce nejsou žádná data.');
}

$typu = 8; //počet typů aktivit
while ($r = mysqli_fetch_assoc($o)) {
    $pocet = $r['pocet'];
    $pocty = array_slice($r, 2, 8);
    //$pocty=array(1,0,0,0,0,0,0,0);
    //$pocet=array_sum($pocty);
    rsort($pocty, SORT_NUMERIC);
    $max = ($pocet - $pocty[0]) / ($pocet * ($typu - 1));
    $nPocty = [];
    for ($i = 1; $i < $typu; $i++) { //první počet přeskočit
        if ($pocty[$i] / $pocet > $max) {
            $nPocty[] = $max;
        } else {
            $nPocty[] = $pocty[$i] / $pocet;
        }
    }
    $divz = array_sum($nPocty) * $typu / ($typu - 1); //výsledná míra diverzifikace 0.0 - 1.0

    $ro = $pocty;
    $ro[] = ' ';
    $ro[] = $divz;
    //fputcsv($out,$ro,$CSV_SEP);

    $raditPodle[] = $divz;
    $raditCo[] = ['id_uzivatele' => $r['id_uzivatele'], 'diverzifikace' => $divz];
}
array_multisort($raditPodle, $raditCo);

//header('Content-type: application/csv; charset=utf-8');
//header('Content-Disposition: attachment; filename="'.$NAZEV_SKRIPTU.'.csv"');
//echo(chr(0xEF).chr(0xBB).chr(0xBF)); //BOM bajty pro nastavení UTF-8 ve výsledném souboru
//$out=fopen('php://output','w'); //získáme filedescriptor výstupu stránky pro použití v fputcsv
$krok = 0.03;
$pocet = 0;
$limit = 0.0;
$vysledky = [];
foreach ($raditCo as $r) {
    if ($r['diverzifikace'] > $limit) {
        $vysledky[$limit . ' '] = $pocet;
        $pocet = 0;
        $limit += $krok;
    }
    $pocet++;
}
$graf = '';
$i = 0;
foreach ($vysledky as $dvz => $vysledek) {
    $graf .= '<div>' .
        '<div style="height:' . round($vysledek * 1.5) . 'px"></div>' .
        $vysledek . '<br />' . (($i & 1) ? '<br />' : '') .
        $dvz . '<br />' . (($i & 1) ? '' : '<br />') .
        '</div>';
    $i++;
}

//fclose($out);

?>

<style>
    .graf {
        background-color: #eee;
        padding: 10px;
        font-family: Arial, sans-serif;
    }

    .graf > div {
        display: inline-block;
        margin: 3px;
        font: 10px Arial;
        line-height: 20px;
        text-align: center;
        max-width: 20px;
    }

    .graf > div > div {
        width: 20px;
        background-color: #74BAFF;
    }
</style>

<div class="graf">
    <h3>Rozložení aktivit</h3>
    <p>Rozsah je od 0.0=jediná aktivita po 1.0=všechny aktivity všech typů zcela rovnoměrně.</p>
    <?php echo $graf ?>
</div>


