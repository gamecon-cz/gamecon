<?php

$this->q("

INSERT INTO r_zidle_soupis (id_zidle, jmeno_zidle, popis_zidle) VALUES
(20, 'Správce financí GC', 'Právo na přístup do sekce promlčení zůstatků');

INSERT INTO r_prava_soupis (id_prava, jmeno_prava, popis_prava) VALUES
(110, 'Administrace - Promlčení zůstatků', '');

INSERT INTO r_prava_zidle (id_zidle, id_prava) VALUES
(20, 110);

INSERT INTO r_uzivatele_zidle (id_uzivatele, id_zidle) VALUES
(53, 20);

");
