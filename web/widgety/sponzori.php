<?php

declare(strict_types=1);

use Gamecon\Web\ZpracovaneObrazky;

// Loga sponzorů a partnerů do textu stránky, `(widget:sponzori)`. Stejná loga jako na stránce /sponzori.

/** @var \Gamecon\XTemplate\XTemplate $t */
ZpracovaneObrazky::logaSponzoruPrehled()->vypisDoSablony($t, 'sponzori.sponzor');
ZpracovaneObrazky::logaPartneruPrehled()->vypisDoSablony($t, 'sponzori.partner');
