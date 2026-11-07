<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// The newsletter tables are mapped by Doctrine entities and the baseline 000.php does not create them, so an
// empty database (the tests) lacked them while production and the anonymized database have them. A no-op there.
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS `newsletter_prihlaseni`
(
  `id_newsletter_prihlaseni` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email`                    varchar(512) NOT NULL,
  `kdy`                      datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_newsletter_prihlaseni`),
  UNIQUE KEY `UNIQ_email` (`email`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_czech_ci
SQL);

$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS `newsletter_prihlaseni_log`
(
  `id_newsletter_prihlaseni_log` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email`                        varchar(512) NOT NULL,
  `kdy`                          datetime NOT NULL DEFAULT current_timestamp(),
  `stav`                         varchar(127) NOT NULL,
  PRIMARY KEY (`id_newsletter_prihlaseni_log`),
  KEY `IDX_email` (`email`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_czech_ci
SQL);
