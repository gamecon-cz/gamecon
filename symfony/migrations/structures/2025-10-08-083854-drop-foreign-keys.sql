ALTER TABLE reporty_log_pouziti DROP FOREIGN KEY IF EXISTS FK_reporty_log_pouziti_to_uzivatele_hodnoty;
ALTER TABLE reporty_log_pouziti DROP FOREIGN KEY IF EXISTS FK_reporty_log_pouziti_to_reporty;
ALTER TABLE uzivatele_role_podle_rocniku DROP FOREIGN KEY IF EXISTS FK_uzivatele_role_podle_rocniku_to_uzivatele_hodnoty;
ALTER TABLE uzivatele_role_podle_rocniku DROP FOREIGN KEY IF EXISTS uzivatele_role_podle_rocniku_ibfk_1;
ALTER TABLE uzivatele_role_podle_rocniku DROP FOREIGN KEY IF EXISTS uzivatele_role_podle_rocniku_ibfk_2;
ALTER TABLE google_api_user_tokens DROP FOREIGN KEY IF EXISTS FK_google_api_user_tokens_to_uzivatele_hodnoty;
ALTER TABLE platby DROP FOREIGN KEY IF EXISTS FK_platby_id_uzivatele_to_uzivatele_hodnoty;
ALTER TABLE platby DROP FOREIGN KEY IF EXISTS FK_platby_provedl_to_uzivatele_hodnoty;
ALTER TABLE platby DROP FOREIGN KEY IF EXISTS platby_ibfk_2;
ALTER TABLE platby DROP FOREIGN KEY IF EXISTS platby_ibfk_3;
ALTER TABLE log_udalosti DROP FOREIGN KEY IF EXISTS FK_log_udalosti_to_uzivatele_hodnoty;
ALTER TABLE uzivatele_role DROP FOREIGN KEY IF EXISTS FK_uzivatele_role_uzivatele_hodnoty;
ALTER TABLE uzivatele_role DROP FOREIGN KEY IF EXISTS FK_uzivatele_role_role_seznam;
ALTER TABLE novinky DROP FOREIGN KEY IF EXISTS FK_novinky_to_texty;
ALTER TABLE google_drive_dirs DROP FOREIGN KEY IF EXISTS FK_google_drive_dirs_to_uzivatele_hodnoty;
ALTER TABLE shop_nakupy DROP FOREIGN KEY IF EXISTS shop_nakupy_ibfk_1;
ALTER TABLE shop_nakupy DROP FOREIGN KEY IF EXISTS shop_nakupy_ibfk_2;
ALTER TABLE shop_nakupy DROP FOREIGN KEY IF EXISTS shop_nakupy_ibfk_3;
ALTER TABLE shop_nakupy DROP FOREIGN KEY IF EXISTS shop_nakupy_ibfk_4;
ALTER TABLE shop_nakupy DROP FOREIGN KEY IF EXISTS shop_nakupy_ibfk_5;
ALTER TABLE shop_nakupy DROP FOREIGN KEY IF EXISTS shop_nakupy_ibfk_6;
ALTER TABLE shop_nakupy DROP FOREIGN KEY IF EXISTS shop_nakupy_ibfk_7;
ALTER TABLE shop_nakupy DROP FOREIGN KEY IF EXISTS FK_shop_nakupy_to_shop_predmety;
ALTER TABLE shop_nakupy DROP FOREIGN KEY IF EXISTS FK_shop_nakupy_to_uzivatele_hodnoty;
ALTER TABLE slevy DROP FOREIGN KEY IF EXISTS FK_slevy_provedl_to_uzivatele_hodnoty;
ALTER TABLE slevy DROP FOREIGN KEY IF EXISTS FK_slevy_to_uzivatele_hodnoty;
ALTER TABLE medailonky DROP FOREIGN KEY IF EXISTS FK_medailonky_to_uzivatele_hodnoty;
ALTER TABLE akce_stavy_log
    DROP FOREIGN KEY IF EXISTS FK_akce_stavy_log_to_akce_seznam,
    DROP FOREIGN KEY IF EXISTS FK_akce_stavy_log_to_akce_stav;
ALTER TABLE uzivatele_url DROP FOREIGN KEY IF EXISTS FK_uzivatele_url_to_uzivatele_hodnoty;
ALTER TABLE systemove_nastaveni_log DROP FOREIGN KEY IF EXISTS FK_systemove_nastaveni_log_to_systemove_nastaveni;
ALTER TABLE systemove_nastaveni_log DROP FOREIGN KEY IF EXISTS FK_systemove_nastaveni_log_to_uzivatele_hodnoty;
ALTER TABLE role_texty_podle_uzivatele DROP FOREIGN KEY IF EXISTS FK_role_texty_podle_uzivatele_to_uzivatele_hodnoty;
ALTER TABLE shop_nakupy_zrusene DROP FOREIGN KEY IF EXISTS FK_zrusene_objednavky_to_uzivatele_hodnoty;
ALTER TABLE shop_nakupy_zrusene DROP FOREIGN KEY IF EXISTS FK_zrusene_objednavky_to_shop_predmety;
ALTER TABLE prava_role DROP FOREIGN KEY IF EXISTS FK_prava_role_to_role_seznam;
ALTER TABLE prava_role DROP FOREIGN KEY IF EXISTS FK_prava_role_to_r_prava_soupis;
ALTER TABLE sjednocene_tagy DROP FOREIGN KEY IF EXISTS FK_sjednocene_tagy_to_kategorie_sjednocenych_tagu;
ALTER TABLE obchod_bunky DROP FOREIGN KEY IF EXISTS FK_obchod_bunky_to_obchod_mrizky;
ALTER TABLE uzivatele_role_log DROP FOREIGN KEY IF EXISTS FK_uzivatele_role_log_to_uzivatele_hodnoty;
ALTER TABLE uzivatele_role_log DROP FOREIGN KEY IF EXISTS FK_uzivatele_role_log_to_role_seznam;
ALTER TABLE akce_typy DROP FOREIGN KEY IF EXISTS FK_akce_typy_to_stranka_o;
ALTER TABLE akce_seznam DROP FOREIGN KEY IF EXISTS FK_akce_seznam_akce_lokace;
ALTER TABLE akce_seznam DROP FOREIGN KEY IF EXISTS FK_akce_seznam_to_akce_instance;
ALTER TABLE akce_seznam DROP FOREIGN KEY IF EXISTS FK_akce_seznam_to_akce_stav;
ALTER TABLE akce_seznam DROP FOREIGN KEY IF EXISTS FK_akce_seznam_to_popis;
ALTER TABLE akce_seznam DROP FOREIGN KEY IF EXISTS FK_akce_seznam_zamcel_to_uzivatele_hodnoty;
ALTER TABLE kategorie_sjednocenych_tagu DROP FOREIGN KEY IF EXISTS FK_kategorie_sjednocenych_tagu_to_kategorie_sjednocenych_tagu;
ALTER TABLE akce_prihlaseni_spec
    DROP FOREIGN KEY IF EXISTS akce_prihlaseni_spec_ibfk_5,
    DROP FOREIGN KEY IF EXISTS akce_prihlaseni_spec_ibfk_6,
    DROP FOREIGN KEY IF EXISTS akce_prihlaseni_spec_ibfk_7,
    DROP FOREIGN KEY IF EXISTS akce_prihlaseni_spec_ibfk_8,
    DROP FOREIGN KEY IF EXISTS akce_prihlaseni_spec_ibfk_9,
    DROP FOREIGN KEY IF EXISTS akce_prihlaseni_spec_ibfk_10,
    DROP FOREIGN KEY IF EXISTS FK_akce_prihlaseni_spec_to_uzivatele_hodnoty,
    DROP FOREIGN KEY IF EXISTS FK_akce_prihlaseni_spec_to_akce_prihlaseni_stavy,
    DROP FOREIGN KEY IF EXISTS FK_akce_prihlaseni_spec_to_akce_seznam;
ALTER TABLE akce_prihlaseni
    DROP FOREIGN KEY IF EXISTS FK_akce_prihlaseni_to_akce_seznam,
    DROP FOREIGN KEY IF EXISTS FK_akce_prihlaseni_to_uzivatele_hodnoty,
    DROP FOREIGN KEY IF EXISTS FK_akce_prihlaseni_to_akce_prihlaseni_stavy,
    DROP FOREIGN KEY IF EXISTS akce_prihlaseni_ibfk_4,
    DROP FOREIGN KEY IF EXISTS akce_prihlaseni_ibfk_5,
    DROP FOREIGN KEY IF EXISTS akce_prihlaseni_ibfk_6;
ALTER TABLE akce_organizatori
    DROP FOREIGN KEY IF EXISTS akce_organizatori_ibfk_3,
    DROP FOREIGN KEY IF EXISTS akce_organizatori_ibfk_4,
    DROP FOREIGN KEY IF EXISTS akce_organizatori_ibfk_5,
    DROP FOREIGN KEY IF EXISTS akce_organizatori_ibfk_6,
    DROP FOREIGN KEY IF EXISTS FK_akce_organizatori_to_akce_seznam,
    DROP FOREIGN KEY IF EXISTS FK_akce_organizatori_to_uzivatele_hodnoty;
ALTER TABLE akce_prihlaseni_log
    DROP FOREIGN KEY IF EXISTS FK_akce_prihlaseni_log_to_akce_seznam,
    DROP FOREIGN KEY IF EXISTS FK_akce_prihlaseni_log_to_uzivatele_hodnoty;
ALTER TABLE hromadne_akce_log DROP FOREIGN KEY IF EXISTS FK_hromadne_akce_log_to_uzivatele_hodnoty;
ALTER TABLE mutex DROP FOREIGN KEY IF EXISTS FK_mutex_to_uzivatele_hodnoty;
ALTER TABLE ubytovani DROP FOREIGN KEY IF EXISTS FK_ubytovani_to_uzivatele_hodnoty;
ALTER TABLE ubytovani DROP FOREIGN KEY IF EXISTS ubytovani_ibfk_2;
ALTER TABLE ubytovani DROP FOREIGN KEY IF EXISTS ubytovani_ibfk_3;
ALTER TABLE akce_import
    DROP FOREIGN KEY IF EXISTS akce_import_ibfk_1,
    DROP FOREIGN KEY IF EXISTS fk_akce_import_to_uzivatele_hodnoty;
ALTER TABLE akce_sjednocene_tagy DROP FOREIGN KEY IF EXISTS FK_akce_sjednocene_tagy_to_sjednocene_tagy;
ALTER TABLE akce_instance DROP FOREIGN KEY IF EXISTS akce_instance_ibfk_1;
ALTER TABLE akce_instance DROP FOREIGN KEY IF EXISTS FK_akce_instance_to_akce_seznam;
ALTER TABLE reporty_log_pouziti DROP FOREIGN KEY IF EXISTS id_reportu;
ALTER TABLE reporty_log_pouziti DROP FOREIGN KEY IF EXISTS id_uzivatele;
