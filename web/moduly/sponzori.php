<?php

use Gamecon\Web\ImagesProcessed;

/** @var Modul $this */
/** @var \Gamecon\XTemplate\XTemplate $t */

ImagesProcessed::logaSponzoruPrehled()->vypisDoSablony($t, 'sponzori.sponzor');
ImagesProcessed::logaPartneruPrehled()->vypisDoSablony($t, 'sponzori.partner');
$t->parse('sponzori');
