<?php

$this->q("DELETE FROM r_prava_zidle  WHERE id_prava = 1009");
$this->q("DELETE FROM r_prava_soupis WHERE id_prava = 1009");

$this->q("
INSERT INTO `r_prava_soupis` (`id_prava`, `jmeno_prava`, `popis_prava`) VALUES (1028, 'Bez slevy za aktivity', 'Nedostává slevu za vedení aktivit ani účast na tech. aktivitách');
");
