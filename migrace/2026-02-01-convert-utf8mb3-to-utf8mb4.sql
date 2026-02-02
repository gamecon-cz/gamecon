/* Convert all tables from utf8mb3 to utf8mb4 */

/* This migration converts all remaining tables that still use utf8mb3 charset
   to utf8mb4, which is the modern standard and supports full Unicode including
   emojis and other 4-byte characters. */

ALTER TABLE `akce_import` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `akce_instance` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `akce_organizatori` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `akce_prihlaseni` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `akce_prihlaseni_log` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `akce_prihlaseni_spec` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `akce_prihlaseni_stavy` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `akce_sjednocene_tagy` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `akce_stav` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `akce_stavy_log` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `akce_typy` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `google_api_user_tokens` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `google_drive_dirs` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `hromadne_akce_log` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `kategorie_sjednocenych_tagu` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `log_udalosti` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `lokace` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `medailonky` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `migrations` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `mutex` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `novinky` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `obchod_bunky` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `obchod_mrizky` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `platby` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `prava_role` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `reporty` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `reporty_log_pouziti` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `reporty_quick` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `role_seznam` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `role_texty_podle_uzivatele` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `r_prava_soupis` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `shop_nakupy` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
/* shop_nakupy_zrusene: Table uses database default charset (utf8mb4) from creation */
ALTER TABLE `shop_predmety` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `sjednocene_tagy` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `slevy` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `stranky` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `systemove_nastaveni` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `systemove_nastaveni_log` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `ubytovani` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `uzivatele_role` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `uzivatele_role_log` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `uzivatele_slucovani_log` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `uzivatele_url` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
ALTER TABLE `_vars` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
