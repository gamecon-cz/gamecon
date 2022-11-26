<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE akce_prihlaseni_spec
  DROP FOREIGN KEY `akce_prihlaseni_spec_ibfk_1`
SQL
);

$this->q(<<<SQL
ALTER TABLE akce_prihlaseni_spec
   ADD FOREIGN KEY (`id_akce`) REFERENCES `akce_seznam` (`id_akce`) ON UPDATE CASCADE ON DELETE CASCADE
SQL
);
