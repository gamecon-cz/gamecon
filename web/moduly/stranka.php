<?php

use Gamecon\Web\Info;

/** @var Stranka|null $stranka */

if (empty($stranka)) {
    include __DIR__ . '/nenalezeno.php';
    return;
}

$this->blackarrowStyl(true);

$this->info()
    ->obrazek($stranka->obrazek())
    ->nazev($stranka->nadpis())
    // the page is found case-insensitively, the canonical address is the stored one
    ->url(Info::getKanonickaUrlZCesty(URL_WEBU, $stranka->url()));

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
