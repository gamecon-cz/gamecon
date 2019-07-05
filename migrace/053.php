<?php
/** @var \Godric\DbMigrations\Migration $this */
$this->q("
ALTER TABLE  `akce_prihlaseni_log` ADD INDEX i_akce_prihlaseni_log_id_akce (  `id_akce` );
ALTER TABLE  `akce_prihlaseni_log` ADD INDEX i_akce_prihlaseni_log_id_uzivatele (  `id_uzivatele` );
");