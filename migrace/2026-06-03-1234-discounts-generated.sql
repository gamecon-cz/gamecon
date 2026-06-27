CREATE TABLE IF NOT EXISTS `discounts_generated`
(
    `id`           bigint unsigned NOT NULL AUTO_INCREMENT,
    `id_uzivatele` bigint unsigned NOT NULL,
    `rok`          int             NOT NULL,
    `castka`       decimal(10, 2)  NOT NULL DEFAULT 0,
    `id_akce`      bigint unsigned          DEFAULT NULL,
    `id_nakupu`    bigint unsigned          DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY (`id_uzivatele`),
    KEY (`id_akce`),
    KEY (`id_nakupu`),
    FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE CASCADE,
    FOREIGN KEY (`id_akce`) REFERENCES `akce_seznam` (`id_akce`) ON DELETE CASCADE,
    FOREIGN KEY (`id_nakupu`) REFERENCES `shop_nakupy` (`id_nakupu`) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb3
  COLLATE=utf8mb3_czech_ci;
