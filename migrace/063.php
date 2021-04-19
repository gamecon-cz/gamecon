<?php

$this->q("
    ALTER TABLE `stranky`
    DROP `url_prefix`;
");

$this->q("
    ALTER TABLE `akce_typy`
    ADD `popis_kratky` varchar(255) NOT NULL;
");
