<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Jeden řádek na jeden odeslaný e-mail - `hromadne_akce_log` drží jen agregát za
// celý běh, takže z něj nejde zjistit, kdo už upomínku dostal. `dluh` se ukládá,
// protože se v čase mění a log má říct, co bylo v odeslaném e-mailu.
$this->q('
CREATE TABLE IF NOT EXISTS `upominka_dluznika_log` (
  `id_log` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_uzivatele` int(11) NOT NULL,
  `typ_upominky` varchar(20) COLLATE utf8_czech_ci NOT NULL,
  `dluh` int(11) NOT NULL,
  `rocnik` smallint(6) NOT NULL,
  `odeslal` int(11) NOT NULL,
  `kdy` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_log`),
  KEY `id_uzivatele` (`id_uzivatele`),
  KEY `rocnik` (`rocnik`),
  KEY `kdy` (`kdy`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci
');
