<?php

use Gamecon\Web\Loga;

/** @var Modul $this */
/** @var \Gamecon\XTemplate\XTemplate $t */

Loga::logaSponzoruPrehled()->vypisDoSablony($t, 'sponzori.sponzor');
Loga::logaPartneruPrehled()->vypisDoSablony($t, 'sponzori.partner');
