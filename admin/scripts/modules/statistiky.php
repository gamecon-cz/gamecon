<?php

/**
 * Stránka statistik GC
 *
 * nazev: Statistiky
 * pravo: 107
 */

use Gamecon\Statistiky\Statistiky;

$zbyva = new DateTime(DEN_PRVNI_DATE);
$zbyva = $zbyva->diff(new DateTime());
$zbyva = $zbyva->format('%a dní') . ' (' . round($zbyva->format('%a') / 7, 1) . ' týdnů)';

$vybraneRoky = array_diff(
    get('rok') ?? range(ROK - 3, ROK),
    [2020] // abychom netrápili databázi hleáním dat pro rok Call of Covid
);
$mozneRoky = range(2009, ROK);

$statistiky = new Statistiky($vybraneRoky, ROK);

$ucast = $statistiky->tabulkaUcastiHtml();
$predmety = $statistiky->tabulkaPredmetuHtml();
$ubytovani = $statistiky->tabulkaUbytovaniHtml();
$ubytovaniKratce = $statistiky->tabulkaUbytovaniKratce();
$jidlo = $statistiky->tabulkaJidlaHtml();
$pohlavi = $statistiky->tabulkaZastoupeniPohlaviHtml();

$prihlaseniData = $statistiky->dataProGrafUcasti(new DateTimeImmutable());

$zarovnaniGrafu = get('zarovnaniGrafu') ?? Statistiky::ZAROVNANI_KE_KONCI_GC;
[
    'nazvyDnu' => $nazvyDnu,
    'zacatkyRegistaci' => $zacatkyRegistaci,
    'zacatkyGc' => $zacatkyGc,
    'konceGc' => $konceGc,
    'prihlaseniProJs' => $prihlaseniProJs,
] = $statistiky->pripravDataProGraf($prihlaseniData, $vybraneRoky, $zarovnaniGrafu);

$indexyDnuZacatkuRegistraci = [];
foreach ($zacatkyRegistaci as $rok => $nazevDneZacatkuRegistrace) {
    if ($rok === ROK && pred(REG_GC_OD)) {
        continue; // registace na letošní GC ještě nezačala
    }
    // nejdřív posbíráme indexy z výsledných názvů dnů, měnit je musíme až později, abychom nepodřízli větev ostatním názvům dnů
    $indexDneZacatkuRegistraciJednohoGc = array_search($nazevDneZacatkuRegistrace, $nazvyDnu);
    $indexyDnuZacatkuRegistraci[$indexDneZacatkuRegistraciJednohoGc][] = $rok;
}
$indexyDnuZacatkuGc = [];
foreach ($zacatkyGc as $rok => $nazevDneZacatkuGc) {
    if ($rok === ROK && pred(GC_BEZI_OD)) {
        continue; // letošní GC ještě nezačal, nechceme ukazovat poslední známé hodnoty s názvem "začátek GC"
    }
    // nejdřív posbíráme indexy z výsledných názvů dnů, měnit je musíme až později, abychom nepodřízli větev ostatním názvům dnů
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
foreach ($indexyDnuZacatkuRegistraci as $indexDneZacatkuRegistraci => $rokyZacinajiciRegistraceStejnyDen) {
    $nazvyDnu[$indexDneZacatkuRegistraci] = $nazvyDnu[$indexDneZacatkuRegistraci] . ", spuštění registrací " . implode(', ', $rokyZacinajiciRegistraceStejnyDen);
}
foreach ($indexyDnuZacatkuGc as $indexDneZacatkuGc => $rokyZacinajiciGcStejnyDen) {
    $nazvyDnu[$indexDneZacatkuGc] = $nazvyDnu[$indexDneZacatkuGc] . ", začátek GC " . implode(', ', $rokyZacinajiciGcStejnyDen);
}
foreach ($indexyDnuKoncuGc as $indexDneKonceGc => $rokyKonciciGcStejnyDen) {
    $nazvyDnu[$indexDneKonceGc] = $nazvyDnu[$indexDneKonceGc] . ", konec GC " . implode(', ', $rokyKonciciGcStejnyDen);
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

        const grafInputs = Array.from(document.querySelectorAll('input[name="rok[]"]:not(:disabled), input[name="zarovnaniGrafu"]:not(:disabled)'))
        grafInputs.forEach(function (grafInput) {
            grafInput.addEventListener('change', function () {
                document.getElementById('vyberGrafuStatistik').submit()
                grafInputs.forEach(function (grafInput) {
                    grafInput.disabled = true
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
    <form action="" style="padding: 0.5em 0" id="vyberGrafuStatistik">
        <fieldset>
            <legend style="padding: 0 0 0.5em; font-style: italic">
                Zarovnání grafu
            </legend>
            <label style="margin-left: 1em">
                <input type="radio" name="zarovnaniGrafu" value="<?= Statistiky::ZAROVNANI_K_ZACATKU_REGISTRACI ?>"
                       <?php if ($zarovnaniGrafu === Statistiky::ZAROVNANI_K_ZACATKU_REGISTRACI) { ?>checked<?php } ?>>
                Začátek registrací na GC
            </label>
            <label>
                <input type="radio" name="zarovnaniGrafu" value="<?= Statistiky::ZAROVNANI_KE_KONCI_GC ?>"
                       <?php if ($zarovnaniGrafu === Statistiky::ZAROVNANI_KE_KONCI_GC) { ?>checked<?php } ?>>
                Konec GC
            </label>
        </fieldset>


        <fieldset style="margin-top: 1em">
            <legend style="padding: 0 0 0.5em; font-style: italic">
                Roky v grafu <span style="font-size: smaller">(počty platí k půlnoci toho dne)</span>
            </legend>
            <?php foreach ($mozneRoky as $moznyRok) {
                $callOfCovid = (int)$moznyRok === 2020;
                ?>
                <span style="min-width: 4em; display: inline-block">
                    <label class="<?php if ($callOfCovid) { ?>hinted<?php } ?>"
                           style="border-bottom: none; padding-right: 0.3em; cursor: <?php if ($callOfCovid) { ?>not-allowed<?php } else { ?>pointer<? } ?>">
                        <input type="checkbox" name="rok[]" value="<?= $moznyRok ?>" style="padding-right: 0.2em"
                               <?php if ($callOfCovid) { ?>disabled<?php } ?>
                               <?php if (in_array($moznyRok, $vybraneRoky, false)) { ?>checked<?php } ?>>
                        <?php if ($callOfCovid) { ?>
                            <span>
                                👾
                                <span class="hint">Call of Covid</span>
                            </span>
                        <?php } ?>
                        <?= $moznyRok ?>
                    </label>
            </span>
            <?php } ?>
        </fieldset>
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
