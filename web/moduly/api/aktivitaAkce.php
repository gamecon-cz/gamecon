<?php

/** @var Uzivatel $u */

use Gamecon\Aktivita\Aktivita;

if (!$u) {
  return ;
}

Aktivita::prihlasovatkoZpracuj($u, $u, reload: false);
