<?php

/** @var Uzivatel|null $u */
/** @var Modul $this */

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

use Gamecon\Aktivita\Program;

if (!$u) {
    throw new Neprihlasen();
}

$this->blackarrowStyl(true);
$this->bezStranky(true);
$program = new Program($systemoveNastaveni, $u, [Program::OSOBNI => $this->param('osobni')]);
$program->tiskToPrint();
