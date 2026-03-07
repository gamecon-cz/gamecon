ALTER TABLE _tables_used_in_view_data_versions DROP FOREIGN KEY _tables_used_in_view_data_versions_ibfk_2;
ALTER TABLE _tables_used_in_view_data_versions DROP FOREIGN KEY _tables_used_in_view_data_versions_ibfk_1;
DROP TABLE _tables_used_in_view_data_versions;
DROP TABLE _vars;
DROP TABLE migrations;
DROP TABLE _table_data_versions;
DROP INDEX id_akce_lokace ON akce_lokace;
ALTER TABLE akce_lokace DROP id_akce_lokace;
ALTER TABLE medailonky CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL;
