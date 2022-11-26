<?php

/** @var Uzivatel|null $u */
/** @var Modul $this */

if (!$u) {
    throw new Neprihlasen();
}

$this->blackarrowStyl(true);
$this->bezStranky(true);
$program = new Program($u, [Program::OSOBNI => $this->param('osobni')]);
$program->tiskToPrint();
