<?php

$this->q("

ALTER TABLE `platby`
CHANGE `provedeno` `provedeno` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;

");
