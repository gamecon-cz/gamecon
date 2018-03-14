<?php

$this->q("
ALTER TABLE `akce_seznam`
ADD `popis_kratky` varchar(255) NOT NULL AFTER `popis`;
");
