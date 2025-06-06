<?php

/** @var Stranka|null $stranka */

if (empty($stranka)) {
    include __DIR__ . '/nenalezeno.php';
    return;
}

$this->blackarrowStyl(true);

$this->info()
    ->obrazek($stranka->obrazek())
    ->nazev($stranka->nadpis());

$typ = $stranka->typ();

?>

<div class="stranka">
    <?php
    if ($typ) { ?>
      <a class="stranka_zpet" href="<?= $typ->url() ?>">zpět na <?= $typ->nazev() ?></a>
    <?php
    } ?>
    <?= $stranka->html() ?>
</div>
