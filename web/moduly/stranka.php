<?php

use Gamecon\Jazyk\AktualniJazyk;

/** @var Stranka|null $stranka */

if (empty($stranka)) {
    include __DIR__ . '/nenalezeno.php';
    return;
}

$this->blackarrowStyl(true);
// přeložená stránka má jazykové verze (hreflang), nepřeložená je pod /en/ kanonicky česká, viz Info
$this->setObsahPrelozeny($stranka->isPrelozena());

$this->info()
    ->obrazek($stranka->obrazek())
    ->nazev($stranka->nadpis());

$typ = $stranka->typ();
$jazykStranky = AktualniJazyk::jazyk();

?>

<div class="stranka">
    <?php
    if ($typ) { ?>
      <a class="stranka_zpet" href="<?= $typ->url() ?>"><?= htmlspecialchars(t('podstranka.zpet', ['linie' => $typ->getNazevVJazyce()])) ?></a>
    <?php
    }
    if ($stranka->getJazykObsahu() !== $jazykStranky) {
        // obsah je pak v <div lang="cs"> (index.xtpl), poznámka je v jazyce stránky
        ?>
      <p class="stranka_jenCesky" lang="<?= $jazykStranky->value ?>"><em><?= htmlspecialchars(t('stranka.jen_cesky')) ?></em></p>
    <?php
    } ?>
    <?= $stranka->html() ?>
</div>
