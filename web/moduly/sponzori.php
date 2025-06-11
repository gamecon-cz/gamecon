<?php

use Gamecon\Web\ZpracovaneObrazky;

/** @var Modul $this */
/** @var \Gamecon\XTemplate\XTemplate $t */

ZpracovaneObrazky::logaSponzoruPrehled()->vypisDoSablony($t, 'sponzori.sponzor');
ZpracovaneObrazky::logaPartneruPrehled()->vypisDoSablony($t, 'sponzori.partner');
$t->parse('sponzori');
