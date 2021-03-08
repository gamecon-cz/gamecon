<?php

$this->blackarrowStyl(true);

$this->info()
  ->obrazek($stranka->obrazek())
  ->nazev($stranka->nadpis());

?>

<div class="stranka">
    <?=$stranka->html()?>
</div>
