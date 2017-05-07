<?php

$this->q("

UPDATE akce_seznam SET typ=3 where typ=14;
DELETE FROM akce_typy where id_typu=14;
UPDATE akce_typy SET typ_1pmn='Parcon a přednášky',typ_1p='Parcon nebo přednáška',typ2pmn='přednášek a parconu',typ_6p='přednáškách a parconu' WHERE id_typu=3;
UPDATE akce_typy SET poradi=-1 where id_typu=10;

");