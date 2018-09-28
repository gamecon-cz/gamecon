<?php

$this->q("
ALTER TABLE `akce_typy`
DROP `titul_orga`, DROP `typ_2pmn`, DROP `typ_6p`, DROP `url_typu`;
");
