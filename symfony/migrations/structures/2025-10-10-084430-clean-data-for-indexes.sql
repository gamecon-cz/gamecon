UPDATE akce_seznam SET typ = 0 WHERE NOT EXISTS(SELECT 1 FROM akce_typy WHERE akce_typy.id_typu = akce_seznam.typ);

DELETE FROM uzivatele_role_podle_rocniku WHERE NOT EXISTS(SELECT 1 FROM role_seznam WHERE role_seznam.id_role = uzivatele_role_podle_rocniku.id_role);

DELETE FROM uzivatele_role_podle_rocniku WHERE NOT EXISTS(SELECT 1 FROM role_seznam WHERE role_seznam.id_role = uzivatele_role_podle_rocniku.id_role);

DELETE FROM uzivatele_role WHERE NOT EXISTS(SELECT 1 FROM role_seznam WHERE role_seznam.id_role = uzivatele_role.id_role);

UPDATE uzivatele_role SET posadil = NULL WHERE NOT EXISTS(SELECT 1 FROM uzivatele_hodnoty WHERE uzivatele_hodnoty.id_uzivatele = uzivatele_role.posadil);

UPDATE akce_prihlaseni_log SET id_zmenil = NULL WHERE NOT EXISTS(SELECT 1 FROM uzivatele_hodnoty WHERE uzivatele_hodnoty.id_uzivatele = akce_prihlaseni_log.id_zmenil);

DELETE FROM akce_sjednocene_tagy WHERE NOT EXISTS(SELECT 1 FROM akce_seznam WHERE akce_seznam.id_akce = akce_sjednocene_tagy.id_akce);

UPDATE uzivatele_role_log SET id_zmenil = NULL WHERE NOT EXISTS(SELECT 1 FROM uzivatele_hodnoty WHERE uzivatele_hodnoty.id_uzivatele = uzivatele_role_log.id_zmenil);
