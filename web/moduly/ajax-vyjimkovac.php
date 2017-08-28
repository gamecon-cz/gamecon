<?php

$this->bezStranky(true);

(new Vyjimkovac(SPEC.'/chyby.sqlite'))->jsZpracuj();
