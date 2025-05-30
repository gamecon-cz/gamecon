<?php

use Gamecon\Web\zpracovaneObrazky;

/** @var Modul $this */
/** @var \Gamecon\XTemplate\XTemplate $t */

zpracovaneObrazky::logaSponzoruPrehled()->vypisDoSablony($t, 'sponzori.sponzor');
zpracovaneObrazky::logaPartneruPrehled()->vypisDoSablony($t, 'sponzori.partner');
$t->parse('sponzori');
