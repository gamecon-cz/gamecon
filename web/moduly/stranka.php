<?php

$this->blackarrowStyl(true);

$this->info()
  ->obrazek($stranka->obrazek())
  ->nazev($stranka->nadpis());

$typ = $stranka->typ();

?>

<div class="stranka stranka_format">
    <?php if ($typ) { ?>
        <a class="stranka_zpet" href="<?=$typ->url()?>">zpět na <?=$typ->nazev()?></a>
    <?php } ?>
    <?=$stranka->html()?>
</div>
