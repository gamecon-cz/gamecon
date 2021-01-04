<?php

$this->blackarrowStyl(true);
$this->bezStranky(true);
$program = new Program($u, ['osobni' => $this->param('osobni')]);
$program->tiskToPrint();
