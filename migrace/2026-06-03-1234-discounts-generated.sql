CREATE TABLE IF NOT EXISTS `discounts_generated`
(
    `id`           bigint unsigned NOT NULL AUTO_INCREMENT primary key,
    `id_uzivatele` bigint unsigned not null references uzivatele_hodnoty (id_uzivatele) on delete cascade,
    `rok`          int             not null,
    `castka`       decimal(10, 2)  not null default 0,
    `id_akce`      bigint unsigned null references akce_seznam (id_akce) on delete cascade,
    `id_nakupu`    bigint unsigned null references shop_nakupy (id_nakupu) on delete cascade
);
