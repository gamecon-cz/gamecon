<?php

$this->q("

ALTER TABLE `uzivatele_hodnoty`
ADD `nechce_maily` datetime NULL COMMENT 'kdy se odhlásil z odebírání mail(er)u' AFTER `souhlas_maily`;

UPDATE uzivatele_hodnoty SET nechce_maily = NOW() WHERE souhlas_maily = 0;

ALTER TABLE `uzivatele_hodnoty`
DROP COLUMN souhlas_maily;  
");
