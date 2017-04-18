<?php

$this->q("

INSERT INTO akce_typy VALUES (14, 'parcon', 'parcon', 'parconu', 'parconu', 'parcon', 'parcon', 77, 'přednášející', 10);
UPDATE akce_typy SET poradi=11 WHERE id_typu=3;
UPDATE akce_typy SET poradi=12 WHERE id_typu=12;
UPDATE akce_typy SET poradi=13 WHERE id_typu=10;

");
