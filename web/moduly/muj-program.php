<?php
if(!$u) {
  header("Location: program");
exit;}

$this->bezOkraju(true);

if($u) Aktivita::prihlasovatkoZpracuj($u);

$program = new Program($u, [
  'osobni'      => true
]);

$a = $u ? $u->koncA() : '';

// hack na staticko-dynamické zobrazení legendy
$legenda = Stranka::zUrl('program-legenda')->html();
$legenda = str_replace('{a}', $u ? $u->koncA() : '', $legenda);
$legenda = str_replace('{n}', $u ? $u->koncovkaNahradnik(): 'ík', $legenda);
if(!$u || !$u->maPravo(P_ORG_AKCI)) $legenda = preg_replace('@.*organizuji.*@', '', $legenda);

?>

<?php $program->css(); ?>

<style>
.legenda hr { display: inline-block; border: none; margin: 0 0 -3px; margin-left: 1em; width: 16px; height: 16px; border-radius: 4px;  }
table.program { box-shadow: 0 0 3px #444; }
.celkovy-program {
  float: right;
  text-transform: uppercase;
  padding: 8px 20px;
  border: solid 1px #444;
  background-color: #d13f3f;
  color: #fff;
  font-weight: bold;
  margin: -8px 0px 0px 24px;
  font-size: 1.2em;
  border-radius: 6px;
}
</style>

<a href="program" style="text-decoration: none">
  <div class="celkovy-program">
    celkový program
  </div>
</a>

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
