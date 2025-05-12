<?php

/**
 * Stránka statistik GC
 *
 * nazev: Statistiky
 * pravo: 9999
 */

//107

/**
 * @var $u Uzivatel
 * @var $systemoveNastaveni SystemoveNastaveni
 * @var $this Modul
 */

use Gamecon\Statistiky\Statistiky;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

$zbyva = new DateTime(DEN_PRVNI_DATE);
$zbyva = $zbyva->diff(new DateTime());
/** @var DateInterval $zbyva */
$zbyva = $zbyva->format('%a dní') . ' (' . round($zbyva->format('%a') / 7, 1) . ' týdnů)';
/** @var string $zbyva */

$vybraneRoky = array_diff(
    get('rok') ?? range($systemoveNastaveni->rocnik() - 3, $systemoveNastaveni->rocnik()),
    [2020], // abychom netrápili databázi hledáním dat pro rok Call of Covid
);
$mozneRoky   = range(ARCHIV_OD, $systemoveNastaveni->rocnik());

$statistiky = new Statistiky($vybraneRoky, $systemoveNastaveni);

$ucast             = $statistiky->tabulkaUcastiHtml();
$predmety          = $statistiky->tabulkaPredmetuHtml();
$ubytovani         = $statistiky->tabulkaUbytovaniHtml();
$ubytovaniKratce   = $statistiky->tabulkaUbytovaniKratce();
$jidlo             = $statistiky->tabulkaJidlaHtml();
$zastoupeniPohlavi = $statistiky->tabulkaZastoupeniPohlaviHtml();

$prihlaseniData = $statistiky->dataProGrafUcasti($systemoveNastaveni->ted());

$zarovnaniGrafu = get('zarovnaniGrafu') ?? Statistiky::ZAROVNANI_KE_KONCI_GC;
[
    'nazvyDnu'           => $nazvyDnu,
    'zacatkyRegistaci'   => $zacatkyRegistaci,
    'zacatkyGc'          => $zacatkyGc,
    'konceGc'            => $konceGc,
    'indexDnesnihoDne'   => $indexDnesnihoDne,
    'indexLetosnihoRoku' => $indexLetosnihoRoku,
    'prihlaseniProJs'    => $prihlaseniProJs,
] = $statistiky->pripravDataProGraf($prihlaseniData, $vybraneRoky, $zarovnaniGrafu);

$indexyDnuZacatkuRegistraci = [];
foreach ($zacatkyRegistaci as $rok => $nazevDneZacatkuRegistrace) {
    if ($rok === $systemoveNastaveni->rocnik() && pred($systemoveNastaveni->prihlasovaniUcastnikuOd())) {
        continue; // registace na letošní GC ještě nezačala
    }
    // nejdřív posbíráme indexy z výsledných názvů dnů, měnit je musíme až později, abychom nepodřízli větev ostatním názvům dnů
    $indexDneZacatkuRegistraciJednohoGc                                = array_search($nazevDneZacatkuRegistrace, $nazvyDnu);
    $indexyDnuZacatkuRegistraci[$indexDneZacatkuRegistraciJednohoGc][] = $rok;
}
$indexyDnuZacatkuGc = [];
foreach ($zacatkyGc as $rok => $nazevDneZacatkuGc) {
    if ($rok === $systemoveNastaveni->rocnik() && pred(GC_BEZI_OD)) {
        continue; // letošní GC ještě nezačal, nechceme ukazovat poslední známé hodnoty s názvem "začátek GC"
    }
    // nejdřív posbíráme indexy z výsledných názvů dnů, měnit je musíme až později, abychom nepodřízli větev ostatním názvům dnů
    $indexDneZacatkuJednohoGc                        = array_search($nazevDneZacatkuGc, $nazvyDnu);
    $indexyDnuZacatkuGc[$indexDneZacatkuJednohoGc][] = $rok;
}
$indexyDnuKoncuGc = [];
foreach ($konceGc as $rok => $nazevDneKonceGc) {
    if ($rok === $systemoveNastaveni->rocnik() && pred(GC_BEZI_DO)) {
        continue; // letošní GC ještě neskončil, nechceme ukazovat poslední známé hodnoty s názvem "konec GC"
    }
    $indexDneKonceJednohoGc                      = array_search($nazevDneKonceGc, $nazvyDnu);
    $indexyDnuKoncuGc[$indexDneKonceJednohoGc][] = $rok;
}
foreach ($indexyDnuZacatkuRegistraci as $indexDneZacatkuRegistraci => $rokyZacinajiciRegistraceStejnyDen) {
    $nazvyDnu[$indexDneZacatkuRegistraci] .= ", spuštění registrací " . implode(', ', $rokyZacinajiciRegistraceStejnyDen);
}
foreach ($indexyDnuZacatkuGc as $indexDneZacatkuGc => $rokyZacinajiciGcStejnyDen) {
    $nazvyDnu[$indexDneZacatkuGc] .= ", začátek GC " . implode(', ', $rokyZacinajiciGcStejnyDen);
}
foreach ($indexyDnuKoncuGc as $indexDneKonceGc => $rokyKonciciGcStejnyDen) {
    $nazvyDnu[$indexDneKonceGc] .= ", konec GC " . implode(', ', $rokyKonciciGcStejnyDen);
}
if ($indexDnesnihoDne >= 0) {
    // highcharts změní HTML takže CSS třídy nelze použít
    $nazvyDnu[$indexDnesnihoDne] = '<span style="font-size: larger; font-weight: bolder; font-style: italic">dnes</span>, ' . $nazvyDnu[$indexDnesnihoDne];
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
      title: { text: null },
      legend: { enabled: false },
      credits: { enabled: false },
      xAxis: {
        categories: <?= json_encode($nazvyDnu) ?>,
        labels: {
          rotation: -90,
          style: { fontSize: '8px' },
        },
        plotLines: [
          {
            color: '#ffffff',
            width: 1,
            value: <?= $pocetDni ?> - 0.5,
          },
          {
            color: colors.at(<?= $indexLetosnihoRoku ?? 0 ?>),
            width: 1,
            value: <?= $indexDnesnihoDne ?? -1 ?>,
          }
        ],
      },
      yAxis: {
        min: 0,
        minRange: 250,
        title: { text: null },
      },
      plotOptions: {
        line: {
          marker: { radius: 2, symbol: 'circle' },
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

<h2>Aktuální statistiky <?= $systemoveNastaveni->rocnik() ?></h2>

<div>
  <p>
    Do gameconu zbývá <?= $zbyva ?>
  </p>
  <div style="float: left"><?= $ucast ?></div>
  <div style="float: left; margin-left: 1em"><?= $zastoupeniPohlavi ?></div>
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
               <?php
               if ($zarovnaniGrafu === Statistiky::ZAROVNANI_K_ZACATKU_REGISTRACI) { ?>checked<?php
        } ?>>
        Začátek registrací na GC
      </label>
      <label>
        <input type="radio" name="zarovnaniGrafu" value="<?= Statistiky::ZAROVNANI_KE_KONCI_GC ?>"
               <?php
               if ($zarovnaniGrafu === Statistiky::ZAROVNANI_KE_KONCI_GC) { ?>checked<?php
        } ?>>
        Konec GC
      </label>
    </fieldset>


    <fieldset style="margin-top: 1em">
      <legend style="padding: 0 0 0.5em; font-style: italic">
        Roky v grafu <span style="font-size: smaller">(počty platí k půlnoci toho dne)</span>
      </legend>
        <?php
        foreach ($mozneRoky as $moznyRok) {
            $callOfCovid = (int)$moznyRok === 2020;
            ?>
          <span style="min-width: 4em; display: inline-block">
                    <label class="<?php
                    if ($callOfCovid) { ?>hinted<?php
                    } ?>"
                           style="border-bottom: none; padding-right: 0.3em; cursor: <?php
                           if ($callOfCovid) { ?>not-allowed<?php
                           } else { ?>pointer<?php
                           } ?>">
                        <input type="checkbox" name="rok[]" value="<?= $moznyRok ?>" style="padding-right: 0.2em"
                               <?php
                               if ($callOfCovid) { ?>disabled<?php
                        } ?>
                               <?php
                               if (in_array($moznyRok, $vybraneRoky, false)) { ?>checked<?php
                        } ?>>
                        <?php
                        if ($callOfCovid) { ?>
                          <span>
                                👾
                                <span class="hint">Call of Covid</span>
                            </span>
                        <?php
                        } ?>
                        <?= $moznyRok ?>
                    </label>
            </span>
        <?php
        } ?>
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
    .dlouhodobeStatistiky > div {
        margin-bottom: 2em;
    }

    .dlouhodobeStatistiky th:first-child {
        width: 110px;
    }

    .dlouhodobeStatistiky th:nth-child(12), .dlouhodobeStatistiky td:nth-child(12) /* 2019 */
    {
        border-right: dotted grey;
    }
</style>

<div class="dlouhodobeStatistiky">
  <div class="responzivni-tabulka odscrolluj-doprava">
      <?= $statistiky->tabulkaHistorieRegistrovaniVsDoraziliHtml() ?>
  </div>

  <div class="responzivni-tabulka odscrolluj-doprava">
      <?= $statistiky->tabulkaLidiNaGcCelkemHtml() ?>
  </div>

  <div class="responzivni-tabulka odscrolluj-doprava">
      <?= $statistiky->tabulkaHistorieProdanychPredmetuHtml() ?>
  </div>

  <div class="responzivni-tabulka odscrolluj-doprava">
      <?= $statistiky->tabulkaHistorieUbytovaniHtml() ?><br>
  </div>
</div>

<script type="text/javascript">
  odscrollujElementyDoprava(document.querySelectorAll('.responzivni-tabulka.odscrolluj-doprava'))
</script>
