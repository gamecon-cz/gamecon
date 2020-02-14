<?php

$this->bezStranky(true);

(new \Gamecon\Vyjimkovac\Vyjimkovac(SPEC.'/chyby.sqlite'))->jsZpracuj();
