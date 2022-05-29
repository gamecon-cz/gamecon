<?php

/**
 * Stránka statistik GC
 *
 * nazev: Statistiky
 * pravo: 107
 */

use Gamecon\Statistiky\Statistiky;
use Gamecon\Zidle;
use Gamecon\Pravo;

// tabulka účasti
$sledovaneZidle = array_merge(
    [Zidle::PRIHLASEN_NA_LETOSNI_GC, Zidle::PRITOMEN_NA_LETOSNIM_GC],
    dbOneArray('SELECT id_zidle FROM r_prava_zidle WHERE id_prava = $0', [Pravo::ZOBRAZOVAT_VE_STATISTIKACH_V_TABULCE_UCASTI])
);

$ucast = tabMysql(dbQuery('
  SELECT
    jmeno_zidle as " ",
    COUNT(uzivatele_zidle.id_uzivatele) as Celkem,
    COUNT(z_prihlasen.id_zidle) as Přihlášen
  FROM r_zidle_soupis AS zidle
  LEFT JOIN r_uzivatele_zidle AS uzivatele_zidle ON zidle.id_zidle = uzivatele_zidle.id_zidle
  LEFT JOIN r_uzivatele_zidle AS z_prihlasen ON
    z_prihlasen.id_zidle = $1 AND
    z_prihlasen.id_uzivatele = uzivatele_zidle.id_uzivatele
  WHERE zidle.id_zidle IN ($0)
  GROUP BY zidle.id_zidle, zidle.jmeno_zidle
  ORDER BY SUBSTR(zidle.jmeno_zidle, 1, 10), zidle.id_zidle
', [
    $sledovaneZidle,
    Zidle::PRIHLASEN_NA_LETOSNI_GC,
]));

// tabulky nákupů
$predmety = tabMysql(dbQuery('
  SELECT
    shop_predmety.nazev Název,
    shop_predmety.model_rok Model,
    COUNT(shop_nakupy.id_predmetu) Počet
  FROM shop_nakupy
  JOIN shop_predmety ON shop_nakupy.id_predmetu = shop_predmety.id_predmetu
  WHERE shop_nakupy.rok=' . ROK . ' AND (shop_predmety.typ=1 OR shop_predmety.typ=3)
  GROUP BY shop_nakupy.id_predmetu
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
  WHERE n.rok=" . ROK . " AND (p.typ=2)
  GROUP BY p.ubytovani_den
UNION ALL
  SELECT 'neubytovaní' as Den, COUNT(*) as Počet
  FROM r_uzivatele_zidle z
  LEFT JOIN(
    SELECT n.id_uzivatele
    FROM shop_nakupy n
    JOIN shop_predmety p ON(n.id_predmetu=p.id_predmetu AND p.typ=2)
    WHERE n.rok=" . ROK . "
    GROUP BY n.id_uzivatele
  ) nn ON(nn.id_uzivatele=z.id_uzivatele)
  WHERE id_zidle=" . ZIDLE_PRIHLASEN . " AND ISNULL(nn.id_uzivatele)
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
  WHERE uz.id_zidle = " . ZIDLE_PRIHLASEN . "
"));

$zbyva = new DateTime(DEN_PRVNI_DATE);
$zbyva = $zbyva->diff(new DateTime());
$zbyva = $zbyva->format('%a dní') . ' (' . round($zbyva->format('%a') / 7, 1) . ' týdnů)';

$vybraneRoky = array_diff(
    $_GET['rok'] ?? range(ROK - 3, ROK),
    [2020] // abychom netrápili databázi hleáním dat pro rok Call of Covid
);
$mozneRoky = range(2012, ROK);

$prihlaseniData = (new Statistiky($vybraneRoky))->data(new DateTimeImmutable());

$pocetDni = 0;
$nazvyDnu = [];
$zacatkyGc = [];
$konceGc = [];
$prihlaseniProJs = [];
foreach ($prihlaseniData as $rok => $dataJednohoRoku) {
    if ((int)$rok === 2020) {
        continue; // Call of Covid
    }
    if (in_array($rok, $vybraneRoky, false)) {
        array_unshift($dataJednohoRoku, 0); // aby graf začínal pěkne na nule
//        $dataJednohoRoku[] = end($dataJednohoRoku); // zopakujeme posledni den, opět aby byl hezčí graf
        $prihlaseniProJs[] = [
            'name' => "Přihlášení $rok",
            'data' => array_values($dataJednohoRoku) // JS knihovna vyžaduje číselné indexování
        ];
        $dnyJednohoRoku = array_keys($dataJednohoRoku);
        $nazvyDnuJednohoRoku = [];
        $zacatekGcRoku = \Gamecon\Cas\DateTimeGamecon::spocitejZacatekGameconu($rok)->formatDatumDb();
        $konecGcRoku = \Gamecon\Cas\DateTimeGamecon::spocitejKonecGameconu($rok)->formatDatumDb();
        foreach ($dnyJednohoRoku as $indexDne => $denJednohoRoku) {
            // index 0 je vynucená nula přes array_unshift, index 1 jsou všechny dny před registrací, index 2 je otevření registrací
            if ($indexDne <= 1) {
                $nazvyDnuJednohoRoku[] = 'před registracemi';
            } elseif ($indexDne === 2) {
                $nazvyDnuJednohoRoku[] = 'začátek registrací'; // první den registrací
            } else {
                $denRegistraci = $indexDne - 1;
                $nazvyDnuJednohoRoku[] = "den $denRegistraci.";
            }
            if ($zacatekGcRoku === $denJednohoRoku) {
                // naposledy vytvořený název jednoho dne je zároveň i dnem začátku GC
                $prvniDenGcRoku = end($nazvyDnuJednohoRoku);
                $zacatkyGc[$rok] = $prvniDenGcRoku;
            }
            if ($konecGcRoku === $denJednohoRoku) {
                // naposledy vytvořený název jednoho dne je zároveň i dnem konce GC
                $posledniDenGcRoku = end($nazvyDnuJednohoRoku);
                $konceGc[$rok] = $posledniDenGcRoku;
            }
        }
        $nazvyDnu = array_unique(array_merge($nazvyDnu, $nazvyDnuJednohoRoku));
    }
}
$indexyDnuZacatkuGc = [];
foreach ($zacatkyGc as $rok => $nazevDneZacatkuGc) {
    if ($rok === ROK && pred(GC_BEZI_OD)) {
        continue; // letošní GC ještě nezačal, nechceme ukazovat poslední známé hodnoty s názvem "začátek GC"
    }
    // nejdřív posbíráme indexy z výsledných názvů dnů, měnit je musíme až později, abychom nepodřízli větev názvům dnů s koncem GC
    $indexDneZacatkuJednohoGc = array_search($nazevDneZacatkuGc, $nazvyDnu);
    $indexyDnuZacatkuGc[$indexDneZacatkuJednohoGc][] = $rok;
}
$indexyDnuKoncuGc = [];
foreach ($konceGc as $rok => $nazevDneKonceGc) {
    if ($rok === ROK && pred(GC_BEZI_DO)) {
        continue; // letošní GC ještě neskončil, nechceme ukazovat poslední známé hodnoty s názvem "konec GC"
    }
    $indexDneKonceJednohoGc = array_search($nazevDneKonceGc, $nazvyDnu);
    $indexyDnuKoncuGc[$indexDneKonceJednohoGc][] = $rok;
}
foreach ($indexyDnuZacatkuGc as $indexDneZacatku => $rokyZacinajiciGcStejnyDen) {
    $nazvyDnu[$indexDneZacatku] = $nazvyDnu[$indexDneZacatku] . ", začátek GC " . implode(', ', $rokyZacinajiciGcStejnyDen);
}
foreach ($indexyDnuKoncuGc as $indexDneKonce => $rokyKonciciGcStejnyDen) {
    $nazvyDnu[$indexDneKonce] = $nazvyDnu[$indexDneKonce] . ", konec GC " . implode(', ', $rokyKonciciGcStejnyDen);
}
$pocetDni = count($nazvyDnu);
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
        const colors = [
            '#2fd8b9',
            '#2f7ed8',
            '#8bbc21',
            '#910000',
            '#1aadce',
            '#492970',
            '#f28f43',
            '#77a1e5',
            '#c42525',
            '#a6c96a',
        ]
        $('#vyvojRegu').highcharts({
            chart: {
                type: 'line',
            },
            title: {text: null},
            legend: {enabled: false},
            credits: {enabled: false},
            xAxis: {
                categories: <?= json_encode($nazvyDnu) ?>,
                labels: {
                    rotation: -90,
                    style: {fontSize: '8px'},
                },
                plotLines: [{
                    color: '#cccccc',
                    width: 1,
                    value: <?= $pocetDni ?> - 3.5,
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
            series: <?= json_encode($prihlaseniProJs) ?>,
            colors: colors,
        })

        Array.from(document.querySelectorAll('input[name="rok[]"][checked]:not(:disabled)')).forEach(function (rokInput, index) {
            // pokud by snad barev bylo méně než grafů, tak se začnou opakovat od začátku - proto ten výpočet restartu indexu, když už pro současný barvu nemáme
            rokInput.parentElement.style.backgroundColor = colors[index] || colors[index - colors.length - 1]
        })

        const rokInputs = Array.from(document.querySelectorAll('input[name="rok[]"]:not(:disabled)'))
        rokInputs.forEach(function (rokInput, index) {
            rokInput.addEventListener('change', function () {
                document.getElementById('vyberRokuGrafu').submit()
                rokInputs.forEach(function (rokInput) {
                    rokInput.disabled = true
                })
            })
        })
    })
</script>
<script src="files/highcharts-v4.2.7.js"></script>

<h2>Aktuální statistiky</h2>

<div>
    <p>
        Do gameconu zbývá <?= $zbyva ?>
    </p>
    <div style="float: left"><?= $ucast ?></div>
    <div style="float: left; margin-left: 1em"><?= $pohlavi ?></div>
    <div style="clear: both"></div>
</div>

<p id="vyvojRegu"></p>

<div>
    <form action="" style="padding: 0.5em 0" id="vyberRokuGrafu">
        <legend style="padding: 0 0 0.5em; font-style: italic">
            Roky v grafu
        </legend>
        <span class="hinted" style="float: right">Vysvětlivky ke grafu
            <span class="hint">
                Data z předchozích let jsou převedena tak, aby počet dní do GameConu na loňské křivce odpovídal počtu dní do GameConu na letošní křivce.<br>
                Svislá čára představuje začátek GameConu. Počet platí pro dané datum v 23:59.
            </span>
        </span>
        <?php foreach ($mozneRoky as $moznyRok) {
            $callOfCovid = (int)$moznyRok === 2020;
            ?>
            <span style="min-width: 4em; display: inline-block">
                    <label class="<?php if ($callOfCovid) { ?>hinted<?php } ?>"
                           style="border-bottom: none; padding-right: 0.3em; cursor: <?php if ($callOfCovid) { ?>not-allowed<?php } else { ?>pointer<? } ?>">
                        <input type="checkbox" name="rok[]" value="<?= $moznyRok ?>" style="padding-right: 0.2em"
                               <?php if ((int)$moznyRok === 2020) { ?>disabled<?php } ?>
                               <?php if (in_array($moznyRok, $vybraneRoky, false)) { ?>checked<?php } ?>>
                        <?php if ((int)$moznyRok === 2020) { ?>
                            <span>
                                👾
                                <span class="hint">Call of Covid</span>
                            </span>
                        <?php } ?>
                        <?= $moznyRok ?>
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

    .dlouhodobeStatistiky th:nth-child(12), .dlouhodobeStatistiky td:nth-child(12) /* 2019 */
    {
        border-right: dotted grey;
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

    <?= tabMysqlR(dbQuery(<<<SQL
SELECT 2009 AS '', 43 AS 'Prodané placky', 43 AS 'Prodané kostky', 6 AS 'Prodaná trička'
UNION ALL
SELECT 2010 AS '', 45 AS 'Prodané placky', 45 AS 'Prodané kostky', 8 AS 'Prodaná trička'
UNION ALL
SELECT 2011 AS '', 206 AS 'Prodané placky', 247 AS 'Prodané kostky', 104 AS 'Prodaná trička'
UNION ALL
SELECT 2012 AS '', 224 AS 'Prodané placky', 154 AS 'Prodané kostky', 121 AS 'Prodaná trička'
UNION ALL
SELECT 2013 AS '', 207 AS 'Prodané placky', 192 AS 'Prodané kostky', 139 AS 'Prodaná trička'
UNION ALL
SELECT
    n.rok as '',
    sum(p.nazev LIKE 'Placka%' and n.rok = model_rok) as 'Prodané placky',
    sum(p.nazev LIKE 'Kostka%' and n.rok = model_rok) as 'Prodané kostky',
    sum(p.nazev like 'Tričko%' and n.rok = model_rok) as 'Prodaná trička'
FROM shop_nakupy n
JOIN shop_predmety p ON n.id_predmetu = p.id_predmetu
WHERE n.rok >= 2014 /* starší data z DB nesedí, jsou vložena fixně */
    AND n.rok != 2020 /* Call of covid */
GROUP BY n.rok
ORDER BY ''
SQL
    )) ?>
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
