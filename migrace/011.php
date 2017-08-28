<?php

$this->q('

INSERT INTO r_prava_soupis(id_prava, jmeno_prava, popis_prava) VALUES
(1015, "Středeční noc zdarma", "");

INSERT INTO r_zidle_soupis(id_zidle, jmeno_zidle, popis_zidle) VALUES
(18, "Středeční noc zdarma", "");

INSERT INTO r_prava_zidle(id_zidle, id_prava) VALUES
(18, 1015);

');
