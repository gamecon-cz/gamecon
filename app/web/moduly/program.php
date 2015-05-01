<?php

$this->bezOkraju(true);

if($u) Aktivita::prihlasovatkoZpracuj($u);

$program = new Program($u);
$a = $u ? $u->koncA() : '';

// hack na staticko-dynamické zobrazení legendy
$legenda = Stranka::zUrl('program-legenda')->html();
$legenda = str_replace('{a}', $u ? $u->koncA() : '', $legenda);
if(!$u || !$u->maPravo(P_ORG_AKCI)) $legenda = preg_replace('@.*organizuji.*@', '', $legenda);

?>

<?php $program->css(); ?>

<style>

  table.program { box-shadow: 0 0 3px #444; }
  table.program th:first-child { min-width: 180px; }

  table.program tr { background-color: #fff; }
  table.program tr:nth-child(2n+1) { background-color: #eee; }

  table.program td { border: 0; padding: 0; border-right: solid 1px #ddd; }
  table.program td:last-child { border: 0; }
  table.program td[rowspan] { background-color: #444; border-top: solid 1px #555; color: #fff; }
  table.program td[colspan] div {
    margin: 2px;
    padding: 2px;
    color: #fff;
    border-radius: 6px;
    background-color: #444;
  }
  table.program td.vDalsiVlne { background-color: transparent; }
  table.program td.vDalsiVlne div, .legenda .vDalsiVlne { background-color: #a34; }
  table.program td.plno { background-color: transparent; }
  table.program td.plno div, .legenda .plno { background-color: #bbb; }
  table.program td.prihlasen { background-color: transparent; }
  table.program td.prihlasen div, .legenda .prihlasen { background-color: #bab2d2; }
  table.program td.organizator { background-color: transparent; }
  table.program td.organizator div, .legenda .organizator { background-color: #bad2b2; }

  table.program th {
    border: none;
    border-right: solid 1px #555;
    background-color: #444;
    border-radius: 0 !important;
    padding: 5px 0;
  }

  .legenda hr { display: inline-block; border: none; margin: 0 0 -3px; margin-left: 1em; width: 16px; height: 16px; border-radius: 4px;  }

</style>

<?=$legenda?>

<?php $program->tisk(); ?>

<script>
$(function(){
  var sneaky = new ScrollSneak(location.hostname);
  $('table.program a').each(function(){
    $(this).click(sneaky.sneak);
  });
});
</script>
