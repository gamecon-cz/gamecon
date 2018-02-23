<?php

$this->q("

INSERT INTO r_zidle_soupis (id_zidle, jmeno_zidle, popis_zidle) VALUES
(20, 'Správce financí GC', 'Organizátor, který může nakládat s financemi GC');

INSERT INTO r_prava_soupis (id_prava, jmeno_prava, popis_prava) VALUES
(110, 'Administrace - Promlčení zůstatků', '');

INSERT INTO r_prava_zidle (id_zidle, id_prava) VALUES
(20, 110);

");
