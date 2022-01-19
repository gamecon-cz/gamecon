<?php

/**
 * Stránka statistik GC
 *
 * nazev: Statistiky
 * pravo: 107
 */

use \Gamecon\Cas\DateTimeCz;

// tabulka účasti
$sledovaneZidle = array_merge(
    [Z_PRIHLASEN, Z_PRITOMEN],
    dbOneArray('SELECT id_zidle FROM r_prava_zidle WHERE id_prava = $0', [P_STATISTIKY_UCAST])
);

$ucast = tabMysql(dbQuery('
  SELECT
    jmeno_zidle as " ",
    COUNT(uz.id_uzivatele) as Celkem,
    COUNT(z_prihlasen.id_zidle) as Přihlášen
  FROM r_zidle_soupis z
  LEFT JOIN r_uzivatele_zidle uz ON z.id_zidle = uz.id_zidle
  LEFT JOIN r_uzivatele_zidle z_prihlasen ON
    z_prihlasen.id_zidle = $1 AND
    z_prihlasen.id_uzivatele = uz.id_uzivatele
  WHERE z.id_zidle IN ($0)
  GROUP BY z.id_zidle, z.jmeno_zidle
  ORDER BY SUBSTR(z.jmeno_zidle, 1, 10), z.id_zidle
', [
    $sledovaneZidle,
    Z_PRIHLASEN,
]));

// tabulky nákupů
$predmety = tabMysql(dbQuery('
  SELECT
    p.nazev Název,
    p.model_rok Model,
    COUNT(n.id_predmetu) Počet
  FROM shop_nakupy n
  JOIN shop_predmety p ON(n.id_predmetu=p.id_predmetu)
  WHERE n.rok=' . ROK . ' AND (p.typ=1 OR p.typ=3)
  GROUP BY n.id_predmetu
  -- ORDER BY p.typ, Počet DESC
'));

$ubytovani = tabMysql(dbQuery('
  SELECT
    p.nazev Název,
    COUNT(n.id_predmetu) Počet
  FROM shop_nakupy n
  JOIN shop_predmety p ON(n.id_predmetu=p.id_predmetu)
  WHERE n.rok=' . ROK . ' AND (p.typ=2)
  GROUP BY n.id_predmetu
'));

$ubytovaniKratce = tabMysql(dbQuery("
  SELECT
    SUBSTR(p.nazev,11) Den,
    COUNT(n.id_predmetu) Počet
  FROM shop_nakupy n
  JOIN shop_predmety p ON(n.id_predmetu=p.id_predmetu)
  WHERE n.rok=".ROK." AND (p.typ=2)
  GROUP BY p.ubytovani_den
UNION ALL
  SELECT 'neubytovaní' as Den, COUNT(*) as Počet
  FROM r_uzivatele_zidle z
  LEFT JOIN(
    SELECT n.id_uzivatele
    FROM shop_nakupy n
    JOIN shop_predmety p ON(n.id_predmetu=p.id_predmetu AND p.typ=2)
    WHERE n.rok=".ROK."
    GROUP BY n.id_uzivatele
  ) nn ON(nn.id_uzivatele=z.id_uzivatele)
  WHERE id_zidle=".Z_PRIHLASEN." AND ISNULL(nn.id_uzivatele)
"));

$jidlo = tabMysql(dbQuery('
  SELECT
    TRIM(p.nazev) Název,
    COUNT(n.id_predmetu) Počet,
    COUNT(slevy.id_uzivatele) as Sleva
  FROM shop_nakupy n
  JOIN shop_predmety p ON n.id_predmetu = p.id_predmetu
  LEFT JOIN (
    SELECT uz.id_uzivatele -- id uživatelů s právy uvedenými níž
    FROM r_uzivatele_zidle uz
    JOIN r_prava_zidle pz ON pz.id_zidle = uz.id_zidle AND pz.id_prava IN(' . P_JIDLO_ZDARMA . ', ' . P_JIDLO_SLEVA . ')
    GROUP BY uz.id_uzivatele
  ) slevy ON slevy.id_uzivatele = n.id_uzivatele
  WHERE n.rok = ' . ROK . ' AND p.typ = 4
  GROUP BY n.id_predmetu
  ORDER BY p.ubytovani_den, p.nazev
'));

$pohlavi = tabMysqlR(dbQuery("
  SELECT
    'Počet' as ' ', -- formátování
    SUM(IF(u.pohlavi='m',1,0)) as Muži,
    SUM(IF(u.pohlavi='f',1,0)) as Ženy,
    ROUND(SUM(IF(u.pohlavi='f',1,0))/COUNT(1),2) as Poměr
  FROM r_uzivatele_zidle uz
  JOIN uzivatele_hodnoty u ON(uz.id_uzivatele=u.id_uzivatele)
  WHERE uz.id_zidle = " . Z_PRIHLASEN . "
"));

$zbyva = new DateTime(DEN_PRVNI_DATE);
$zbyva = $zbyva->diff(new DateTime());
$zbyva = $zbyva->format('%a dní') . ' (' . round($zbyva->format('%a') / 7, 1) . ' týdnů)';

// graf účasti
$q = 'SELECT
    DATE(z.posazen) as den,
    COUNT(1) as prihlasen,
    COUNT(IF(YEAR(u.registrovan)=' . ROK . ',1,NULL)) as novy
  FROM r_uzivatele_zidle z
  JOIN uzivatele_hodnoty u USING(id_uzivatele)
  WHERE z.id_zidle=' . Z_PRIHLASEN . '
  GROUP BY DATE(posazen)';
$o = dbQuery($q);
$zacatek = new DateTime(ROK . '-04-29'); // zde ladit, dokud se grafy nezarovnají na poslední den
$pocet = 0;
do {
    $pocet += $r['prihlasen'] ?? 0; // první prázdný ignorovat, další brát "o kolo zpět"
    $r = mysqli_fetch_assoc($o);
    $den = new DateTimeCz($r['den']);
} while ($den->pred($zacatek) && $r['den']); // kontrola dne proti zacyklení
// dny před GC
$dny = '';
$prihlaseniLetos = [];
$konec = new DateTime(GC_BEZI_DO);

$vceraTouhleDobou = new \DateTimeImmutable();
for (
    $den = $zacatek;
    $den <= $konec;
    $den->add(new DateInterval('P1D'))
) {
    $denDb = new DateTime($r['den']);
    if ($r === FALSE) { // z DB už vše vyčteno
        if ($den < $vceraTouhleDobou) { // dnešek nezobrazujeme pokud přibylo 0, včerejšek a dříve už ano
            $prihlaseniLetos[] = $pocet;
        } else {
            $prihlaseniLetos[] = null;
        }
    } else if ($den->getTimestamp() < $denDb->getTimestamp()) {
        $prihlaseniLetos[] = $pocet;
    } else if ($den->getTimestamp() == $denDb->getTimestamp()) {
        $pocet += $r['prihlasen'];
        $prihlaseniLetos[] = $pocet;
        $r = mysqli_fetch_assoc($o);
    } else {
        $prihlaseniLetos[] = null;
    }
    $dny .= '\'' . $den->format('j.n.') . '\',';
}
$dny = '[' . substr($dny, 0, -1) . ']';
$pocetDni = substr_count($dny, ',');

$vybraneRoky = $_GET['rok'] ?? range(ROK - 3, ROK);
$prihlaseniData = require __DIR__ . '/_statistiky_prihlaseni_minulych_let.php';
$prihlaseniData[ROK] = $prihlaseniLetos;
$prihlaseniProJs = [];
foreach ($prihlaseniData as $rok => $data) {
    if (in_array($rok, $vybraneRoky, false)) {
        $prihlaseniProJs[] = ['name' => "Přihlášení $rok", 'data' => $data];
    }
}
$prihlaseniJson = json_encode($prihlaseniProJs);
?>

<style>
    tr td {
        text-align: right;
    }

    tr td:first-child {
        text-align: left;
    }
</style>
<script>
    $(function () {
        $('#vyvojRegu').highcharts({
            chart: {
                type: 'line',
            },
            title: {text: null},
            legend: {enabled: false},
            credits: {enabled: false},
            xAxis: {
                categories: <?=$dny?>,
                labels: {
                    rotation: -90,
                    style: {fontSize: '8px'},
                },
                plotLines: [{
                    color: '#cccccc',
                    width: 1,
                    value: <?=$pocetDni?> - 3.5,
                }],
            },
            yAxis: {
                min: 0,
                minRange: 250,
                title: {text: null},
            },
            plotOptions: {
                line: {
                    marker: {radius: 2, symbol: 'circle'},
                    connectNulls: true,
                    animation: false,
                },
            },
            series: <?= $prihlaseniJson ?>,
            colors: [
                '#2f7ed8',
                '#8bbc21',
                '#910000',
                '#1aadce',
                '#492970',
                '#f28f43',
                '#77a1e5',
                '#c42525',
                '#a6c96a',
            ],
        })
    })
</script>
<script src="files/highcharts-v4.2.7.js"></script>

<h2>Aktuální statistiky</h2>

<div style="float:left; max-width: 25%">
    <?= $ucast ?><br>
    <?= $pohlavi ?><br>
    Do gameconu zbývá <?= $zbyva ?><br><br>
    <span class="hinted">Vysvětlivky ke grafu
        <span class="hint">
            Data z předchozích let jsou převedena tak, aby počet dní do GameConu na loňské křivce odpovídal počtu dní do GameConu na letošní křivce.<br>
            Svislá čára představuje začátek GameConu. Počet platí pro dané datum v 23:59.
        </span>
    </span>
</div>
<div style="float:left;margin-left:20px;width:650px;height:300px" id="vyvojRegu"></div>
<div style="clear:both"></div><br>

<div>
    <form action="" style="padding: 0.5em 0" id="vyberRokuGrafu">
        <legend style="padding: 0 0 0.5em; font-style: italic">
            Roky v grafu
        </legend>
        <?php foreach ($prihlaseniData as $rok => $data) { ?>
            <span style="min-width: 4em; display: inline-block">
                    <label style="padding-right: 0.3em; cursor: pointer">
                        <input type="checkbox" name="rok[]" value="<?= $rok ?>" style="padding-right: 0.2em"
                               onchange="$('#vyberRokuGrafu').submit()"
                               <?php if (in_array($rok, $vybraneRoky, false)) { ?>checked<?php } ?>>
                        <?= $rok ?>
                    </label>
            </span>
        <?php } ?>
    </form>
</div>

<hr>

<div style="float:left"><?= $predmety ?></div>
<div style="float:left;margin-left:20px"><?= $ubytovani ?></div>
<div style="float:left;margin-left:20px"><?= $ubytovaniKratce ?></div>
<div style="float:left;margin-left:20px"><?= $jidlo ?></div>

<div style="clear:both"></div>

<h2>Dlouhodobé statistiky</h2>

<style>
    .dlouhodobeStatistiky th:first-child {
        width: 110px;
    }
</style>
<div class="dlouhodobeStatistiky">
    <table>
        <tr>
            <th></th>
            <th>2009</th>
            <th>2010</th>
            <th>2011</th>
            <th>2012</th>
            <th>2013</th>
            <th>2014</th>
            <th>2015</th>
            <th>2016</th>
            <th>2017</th>
            <th>2018</th>
            <th>2019</th>
            <th>2020</th>
            <th>2021</th>
        </tr>
        <tr>
            <td>Registrovaní</td>
            <td>339</td>
            <td>377</td>
            <td>383</td>
            <td>357</td>
            <td>433</td>
            <td>520</td>
            <td>595</td>
            <td>689</td>
            <td>837</td>
            <td>821</td>
            <td>830</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>Dorazilo</td>
            <td>68?</td>
            <td>350</td>
            <td>339</td>
            <td>319</td>
            <td>389</td>
            <td>470</td>
            <td>536</td>
            <td>605</td>
            <td>769</td>
            <td>739</td>
            <td>754</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>&emsp;z toho studenti</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td>149</td>
            <td>172</td>
            <td>148</td>
            <td>175</td>
            <td>153</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>&emsp;z toho ostatní</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td>152</td>
            <td></td>
            <td>388</td>
            <td>430</td>
            <td>616</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>Podpůrný tým</td>
            <td>43</td>
            <td>45</td>
            <td>71</td>
            <td>74</td>
            <td>88</td>
            <td>109</td>
            <td>111</td>
            <td>133</td>
            <td>186</td>
            <td>176</td>
            <td>185</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>&emsp;organizátoři</td>
            <td>6</td>
            <td>8</td>
            <td>13</td>
            <td>17</td>
            <td>17</td>
            <td>22</td>
            <td>24</td>
            <td>28</td>
            <td>38</td>
            <td>38</td>
            <td>38</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>&emsp;zázemí</td>
            <td>7</td>
            <td>7</td>
            <td>6</td>
            <td>10</td>
            <td>8</td>
            <td>1</td>
            <td>3</td>
            <td>1</td>
            <td>8</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>&emsp;vypravěči</td>
            <td>30</td>
            <td>30</td>
            <td>52</td>
            <td>47</td>
            <td>63</td>
            <td>86</td>
            <td>95</td>
            <td>122</td>
            <td>168</td>
            <td>138</td>
            <td>147</td>
            <td></td>
            <td></td>
        </tr>
    </table>
    <a href="#" onclick="return!$(this).next().toggle()">dotaz</a>
    <pre style="display:none">
  -- všechny staty od Dorazilo níže se počítají z počtu dorazivších
  SELECT 2000 - (id_zidle DIV 100), count(1)
  FROM r_uzivatele_zidle
  JOIN ( -- sekundární židle
    SELECT DISTINCT id_uzivatele FROM r_uzivatele_zidle WHERE id_zidle IN(2,6,7)
  ) orgove USING(id_uzivatele)
  WHERE id_zidle < 0 AND id_zidle MOD 100 = -2
  GROUP BY id_zidle
</pre>
    <br><br>

    <?= tabMysqlR(dbQuery("
  select
    2000-(id_zidle div 100) as '',
    count(id_zidle) 'Lidé na GC celkem',
    sum(pohlavi='m') as '&emsp;z toho muži',
    sum(pohlavi='f') as '&emsp;z toho ženy',
    round(sum(pohlavi='f') / count(id_zidle), 2) as '&emsp;podíl žen'
  from r_uzivatele_zidle
  left join uzivatele_hodnoty using(id_uzivatele)
  where id_zidle < 0
  and id_zidle % 100 = -2
  group by id_zidle
  order by id_zidle desc
")) ?><br>

    <div>
        <style>
            #doplnekProdeje tr > :first-child {
                display: none;
            }
        </style>
        <div style="float:left">
            <table>
                <tr>
                    <th></th>
                    <th>2009</th>
                    <th>2010</th>
                    <th>2011</th>
                    <th>2012</th>
                    <th>2013</th>
                </tr>
                <tr>
                    <td>Prodané placky</td>
                    <td>43</td>
                    <td>45</td>
                    <td>206</td>
                    <td>224</td>
                    <td>207</td>
                </tr>
                <!-- <tr><td>&emsp;před začátkem</td>    <td></td>       <td></td>       <td>135</td>    <td>150</td>    <td>110</td>  </tr> -->
                <!-- <tr><td>&emsp;na místě</td>         <td></td>       <td></td>       <td></td>       <td></td>       <td>9</td>    </tr> -->
                <!-- <tr><td>&emsp;zdarma</td>           <td>43</td>     <td>45</td>     <td>71</td>     <td>74</td>     <td>88</td>   </tr> -->
                <tr>
                    <td>Prodané kostky</td>
                    <td>43</td>
                    <td>45</td>
                    <td>247</td>
                    <td>154</td>
                    <td>192</td>
                </tr>
                <!-- <tr><td>&emsp;před začátkem</td>    <td></td>       <td></td>       <td>176</td>    <td>80</td>     <td>104</td>  </tr> -->
                <!-- <tr><td>&emsp;na místě</td>         <td></td>       <td></td>       <td></td>       <td></td>       <td></td>     </tr> -->
                <!-- <tr><td>&emsp;zdarma</td>           <td>43</td>     <td>45</td>     <td>71</td>     <td>74</td>     <td>88</td>   </tr> -->
                <tr>
                    <td>Prodaná trička</td>
                    <td>6</td>
                    <td>8</td>
                    <td>104</td>
                    <td>121</td>
                    <td>139</td>
                </tr>
                <!-- <tr><td>&emsp;zdarma</td>           <td>6</td>      <td>8</td>      <td>13</td>     <td>17</td>     <td>19</td>   </tr> -->
                <!-- <tr><td>&emsp;za 50%</td>           <td></td>       <td></td>       <td>34</td>     <td>40</td>     <td>35</td>   </tr> -->
                <!-- <tr><td>&emsp;plná cena</td>        <td></td>       <td></td>       <td>57</td>     <td>64</td>     <td>85</td>   </tr> -->
            </table>
        </div>
        <div style="float:left" id="doplnekProdeje">
            <?= tabMysqlR(dbQuery("
      SELECT
        n.rok as '',
        sum(p.nazev LIKE 'Placka%' and n.rok = model_rok) as 'Prodané placky',
        sum(p.nazev LIKE 'Kostka%' and n.rok = model_rok) as 'Prodané kostky',
        sum(p.nazev like 'Tričko%' and n.rok = model_rok) as 'Prodaná trička'
      FROM shop_nakupy n
      JOIN shop_predmety p ON n.id_predmetu = p.id_predmetu
      WHERE n.rok >= 2014 -- starší data z DB nesedí, jsou vložena fixně
      GROUP BY n.rok
      ORDER BY n.rok
    ")) ?>
        </div>
        <div style="clear:both"></div>
    </div>
    <br>

    <?= tabMysqlR(dbQuery("
  select
    n.rok as '',
    sum(nazev like '%lůžák%') as 'Postel',
    sum(nazev like '%lůžák%' and ubytovani_den=0) as '&emsp;středa',
    sum(nazev like '%lůžák%' and ubytovani_den=1) as '&emsp;čtvrtek',
    sum(nazev like '%lůžák%' and ubytovani_den=2) as '&emsp;pátek',
    sum(nazev like '%lůžák%' and ubytovani_den=3) as '&emsp;sobota',
    sum(nazev like '%lůžák%' and ubytovani_den=4) as '&emsp;neděle',
    sum(nazev like 'spacák%') as 'Spacák',
    sum(nazev like 'spacák%' and ubytovani_den=0) as '&emsp;středa ',
    sum(nazev like 'spacák%' and ubytovani_den=1) as '&emsp;čtvrtek ',
    sum(nazev like 'spacák%' and ubytovani_den=2) as '&emsp;pátek ',
    sum(nazev like 'spacák%' and ubytovani_den=3) as '&emsp;sobota ',
    sum(nazev like 'spacák%' and ubytovani_den=4) as '&emsp;neděle ',
    sum(nazev like 'penzion%') as 'Penzion',
    sum(nazev like 'penzion%' and ubytovani_den=0) as '&emsp;středa  ',
    sum(nazev like 'penzion%' and ubytovani_den=1) as '&emsp;čtvrtek  ',
    sum(nazev like 'penzion%' and ubytovani_den=2) as '&emsp;pátek  ',
    sum(nazev like 'penzion%' and ubytovani_den=3) as '&emsp;sobota  ',
    sum(nazev like 'penzion%' and ubytovani_den=4) as '&emsp;neděle  ',
    sum(nazev like 'chata%') as 'Kemp',
    sum(nazev like 'chata%' and ubytovani_den=0) as '&emsp;středa   ',
    sum(nazev like 'chata%' and ubytovani_den=1) as '&emsp;čtvrtek   ',
    sum(nazev like 'chata%' and ubytovani_den=2) as '&emsp;pátek   ',
    sum(nazev like 'chata%' and ubytovani_den=3) as '&emsp;sobota   ',
    sum(nazev like 'chata%' and ubytovani_den=4) as '&emsp;neděle   '
  from shop_nakupy n
  join shop_predmety p using(id_predmetu)
  where p.typ = 2
  group by n.rok
  order by n.rok
")) ?><br>

</div>
