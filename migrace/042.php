<?php

$this->q("
ALTER TABLE `slevy`
CHANGE `provedeno` `provedeno` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `rok`;
");
