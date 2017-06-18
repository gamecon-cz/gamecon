<?php

$this->q("

INSERT INTO r_prava_soupis (id_prava, jmeno_prava, popis_prava) VALUES (1018, 'Nedělní noc zdarma', '');
INSERT INTO r_zidle_soupis (id_zidle, jmeno_zidle, popis_zidle) VALUES (19, 'Nedělní noc zdarma', '');
INSERT INTO r_prava_zidle (id_zidle, id_prava) VALUES (19, 1018);

");
