<?php

$this->q('

DELETE FROM r_prava_zidle WHERE id_prava = 1001;
DELETE FROM r_prava_soupis WHERE id_prava = 1001;
INSERT INTO r_prava_soupis(id_prava, jmeno_prava, popis_prava) VALUES
(1011, "Obyčejné tričko za dosaženou slevu 660", ""),
(1012, "Modré tričko za dosaženou slevu 660", ""),
(1013, "Obyčejné tričko se slevou 50Kč", ""),
(1014, "Modré tričko se slevou 50Kč", "");
UPDATE r_prava_soupis SET jmeno_prava = "Červené tričko zdarma" WHERE id_prava = 1000;

');
