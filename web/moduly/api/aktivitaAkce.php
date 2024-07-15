<?php

/** @var Uzivatel $u */

use Gamecon\Aktivita\Aktivita;

if (!$u) {
  return ;
}

Aktivita::prihlasovatkoZpracujJSON($u, $u);

