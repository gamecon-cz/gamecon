<?php

$this->bezOkraju(true);

/** @type Uzivatel|null $u */
if($u) Aktivita::prihlasovatkoZpracuj($u);

$program = new Program($u, ['osobni' => $this->param('osobni')]);
$a = $u ? $u->koncA() : '';

// hack na staticko-dynamické zobrazení legendy
$legendaStranka = Stranka::zUrl('program-legenda');
$legenda = $legendaStranka ? $legendaStranka->html() : '';
$legenda = str_replace('{a}', $u ? $u->koncA() : '', $legenda);
$legenda = str_replace('{n}', $u && $u->pohlavi() == 'f' ? 'ice' : 'ík', $legenda);
if(!$u || !$u->maPravo(P_ORG_AKCI)) $legenda = preg_replace('@.*organizuji.*@', '', $legenda);

?>

<?php $program->css(); ?>

<style>
.legenda hr { display: inline-block; border: none; margin: 0 0 -3px; margin-left: 1em; width: 16px; height: 16px; border-radius: 4px;  }
table.program { box-shadow: 0 0 3px #444; }
.muj-program {
  float: right;
  display: block;
  text-transform: uppercase;
  padding: 6px 20px;
  border: solid 1px #444;
  background-color: #d13f3f;
  color: #fff;
  font-weight: bold;
  margin: -8px 0px 0px 16px;
  font-size: 14px;
  border-radius: 6px;
}
</style>

<?php require __DIR__ . '/../soubory/program-nahled.html'; ?>

<div class="programNahled_obalProgramu">

  <?php if(!$this->param('osobni')) { ?>
    <div id="programSkryvaniLinii_ovladani" class="programSkryvaniLinii_ovladani">
      <span class="programSkryvaniLinii_popisek">Filtrovat linie: </span>
    </div>
  <?php } ?>

  <a class="muj-program" id="programNahled_externiPrepinac" href="#"></a>
  <?php if($u) { ?>
    <a class="muj-program" target="_blank" href="programKTisku">k tisku</a>
    <?php if($this->param('osobni')) { ?>
      <a class="muj-program" href="program">celkový program</a>
    <?php } else { ?>
      <a class="muj-program" href="muj-program">můj program</a>
    <?php } ?>
  <?php } ?>

  <?=$legenda?>

  <?php $program->tisk(); ?>

</div>

<script>
programSkryvaniLinii($('table.program'), $('#programSkryvaniLinii_ovladani'));
programNahled($('.programNahled_obalNahledu'), $('.programNahled_obalProgramu'), $('.programNahled_odkaz'), $('#programNahled_externiPrepinac'));

$(function(){
  var sneaky = new ScrollSneak(location.hostname);
  $('table.program a').each(function(){
    $(this).click(sneaky.sneak);
  });
});
</script>
