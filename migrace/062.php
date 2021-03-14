<?php

$this->q("
    ALTER TABLE `akce_typy`
    ADD `popis_kratky` varchar(255) NOT NULL;
");
