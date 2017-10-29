<?php

if(!$u) { //jen přihlášení
  echo hlaska('jenPrihlaseni');
  return;
}

$this->bezOkraju(true);

if($u) Aktivita::prihlasovatkoZpracuj($u);

$program = new OsobniProgram($u);
$a = $u ? $u->koncA() : '';

// hack na staticko-dynamické zobrazení legendy
$legenda = Stranka::zUrl('program-legenda')->html();
$legenda = str_replace('{a}', $u ? $u->koncA() : '', $legenda);
if(!$u || !$u->maPravo(P_ORG_AKCI)) $legenda = preg_replace('@.*organizuji.*@', '', $legenda);

?>
<a href="muj-program">Můj program</a>
<a href="muj-program-nahradnik">Můj program s W listy</a>
<?php $program->css(); ?>

<style>
.legenda hr { display: inline-block; border: none; margin: 0 0 -3px; margin-left: 1em; width: 16px; height: 16px; border-radius: 4px;  }
table.program { box-shadow: 0 0 3px #444; }
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
