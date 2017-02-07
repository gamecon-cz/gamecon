<?php

/**
 * Stránka s linky na reporty
 * Reporty jsou obecně neoptimalizovaný kód (cyklické db dotazy apod.), nepočítá
 * se s jejich časově kritickým použitím.
 *
 * nazev: Reporty
 * pravo: 104
 */

$reporty = [
  ['aktivity',          'Historie přihlášení na aktivity',        ['csv', 'html']],
  //['neplatici',         'Neplatiči letos'], // duplikuje bfgr a finance, nemá smysl udržovat
  //['spolupracovnici',   'Spolupracovníci (orgové, info, zázemí, vypravěči+aktivity)'], // neudržované, asi lze zjistit z bfgr
  ['pocty-her',         'Účastníci a počty jejich aktivit'],
  ['pocty-her-graf',    'Graf rozložení rozmanitosti her',        ['html']],
  ['rozesilani-ankety', 'Rozesílání ankety s tokenem',            ['html']],
  ['parovani-ankety',   'Párování ankety a údajů uživatelů',      ['html']],
  ['grafy-ankety',      'Grafy k anketě',                         ['html']],
  ['update-zustatku',   'UPDATE příkaz zůstatků pro letošní GC',  ['html']],
  ['ubytovani',         'Ubytování',                              ['csv', 'html']],
  ['celkova-ucast',     'Celková účast'],
  ['neprihlaseni-vypraveci', 'Nepřihlášení a neubytovaní vypravěči', ['html']],
  ['duplicity',         'Duplicitní uživatelé',                   ['html']],
  ['stravenky',         'Stravenky uživatelů',                    ['html']],
  ['stravenky?ciste',   'Stravenky (bianco)',                     ['html']],
  ['programove-reporty', 'Programový report (2015)', ['csv', 'html']],
  ['zaplnenost-programu-ucastniku', 'Zaplněnost programu účastníků (2015)', ['csv', 'html']],
  ['maily-prihlaseni',  'Maily – přihlášení na GC (vč. unsubscribed)', ['csv', 'html']],
  ['maily-neprihlaseni','Maily – nepřihlášení na GC',             ['csv', 'html']],
  ['maily-vypraveci',   'Maily – vypravěči (vč. unsubscribed)',   ['csv', 'html']],
  ['maily-vsichni',     'Maily – všichni',                        ['csv', 'html']],
  ['celkovy-report',    '<br>Celkový report '.ROK.'<br><br>',     ['csv', 'html']],
];


?>

<h2>Univerzální reporty</h2>

<table class="zvyraznovana">
  <tr>
    <th>Report</th>
    <th colspan="2">Formáty</th>
  </tr>
  <?php foreach($reporty as $r) { ?>
    <tr>
      <td><?=$r[1]?></td>
      <td>
        <?php if(!isset($r[2]) || $r[2][0] == 'csv') { ?>
          <a href="reporty/<?=$r[0]?>">csv</a>
        <?php } ?>
      </td>
      <td>
        <?php if(isset($r[2])) { ?>
          <?php if($r[2][0] == 'html') { ?>
            <a href="reporty/<?=$r[0]?>" target="_blank">html</a>
          <?php } else { ?>
            <a href="reporty/<?=$r[0]?>?format=html" target="_blank">html</a>
          <?php } ?>
        <?php } ?>
      </td>
    </tr>
  <?php } ?>
</table>

<h2>Quick reporty <span class="hinted">ℹ<span class="hint">tyto reporty samy náhodně mizí a nelze tomu zabránit. Proto není možné na ně spoléhat</span></span></h2>

<table class="zvyraznovana">
  <tr>
    <th>Název</th>
    <th></th>
  </tr>
  <?php foreach(dbIterator('SELECT * FROM reporty') as $r) { ?>
  <tr>
    <td><?=$r['nazev']?></td>
    <td><a href="reporty/quick?id=<?=$r['id']?>" class="tlacitko">upravit</a></td>
  </tr>
  <?php } ?>
</table>
