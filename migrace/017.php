<?php

$this->q("

ALTER TABLE `akce_seznam`
ADD `team_kapacita` int(11) NULL COMMENT 'max. počet týmů, pokud jde o další kolo týmové aktivity' AFTER `team_max`;

");
