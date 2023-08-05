<?php

namespace Gamecon\Tests;

use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Tests\Db\DbWrapper;
use Godric\DbMigrations\DbMigrationsConfig;
use Godric\DbMigrations\DbMigrations;


class DBTest
{
  public static function resetujDB(string $databaze)
  {
    // příprava databáze
    $connection = dbConnectTemporary(false);
    dbQuery(sprintf('DROP DATABASE IF EXISTS `%s`', DB_NAME), [], $connection);
    dbQuery(sprintf('CREATE DATABASE IF NOT EXISTS `%s` COLLATE "utf8_czech_ci"', DB_NAME), [], $connection);
    dbQuery(sprintf('USE `%s`', DB_NAME), [], $connection);

    // naimportujeme databázi s už proběhnutými staršími migracemi
    (new \MySQLImport($connection))->load(__DIR__ . '/Db/data/' . $databaze);

    (new DbMigrations(new DbMigrationsConfig([
      'connection'          => $connection, // předpokládá se, že spojení pro testy má administrativní práva
      'migrationsDirectory' => __DIR__ . '/../migrace',
      'doBackups'           => false,
    ])))->run();

    /**
     * pokud chceš vyřadit STRICT_TRANS_TABLES (potlačit "Field 'nazev_akce' doesn't have a default value"), použij @see \Gamecon\Tests\Db\DbTest::$disableStrictTransTables
     * Inspirace @see \Gamecon\Tests\Aktivity\AktivitaTagyTest::setUpBeforeClass
     */
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    AbstractTestDb::setConnection(new DbWrapper());

    /** vynutíme reconnect, hlavně kvůli nastavení ROCNIK v databázi, @see \dbConnect */
    dbClose();
  }

  /**
   * vymaže akce, platby, uživatele, logy, novinky, slevy, texty, ubytovani, nastaveni shopu
   * ostatní věci nutné pro správné fungování GC stránky jako jsou systemove nastaveni role atd. zůstávají vč. popisu stránek
   */
  public static function vycistiDB()
  {
    $query = <<<SQL
      SET FOREIGN_KEY_CHECKS = 0;

      DELETE FROM reporty WHERE skript LIKE "quick-%";

      -- TRUNCATE TABLE akce_lokace;
      -- TRUNCATE TABLE shop_predmety;

      TRUNCATE TABLE akce_import;
      TRUNCATE TABLE akce_instance;
      TRUNCATE TABLE akce_organizatori;
      TRUNCATE TABLE akce_prihlaseni;
      TRUNCATE TABLE akce_prihlaseni_log;
      TRUNCATE TABLE akce_prihlaseni_spec;
      TRUNCATE TABLE akce_seznam;
      TRUNCATE TABLE akce_sjednocene_tagy;
      TRUNCATE TABLE akce_stavy_log;
      TRUNCATE TABLE google_api_user_tokens;
      TRUNCATE TABLE google_drive_dirs;
      TRUNCATE TABLE hromadne_akce_log;
      TRUNCATE TABLE log_udalosti;
      TRUNCATE TABLE medailonky;
      TRUNCATE TABLE mutex;
      TRUNCATE TABLE novinky;
      TRUNCATE TABLE obchod_bunky;
      TRUNCATE TABLE obchod_mrizky;
      TRUNCATE TABLE platby;
      TRUNCATE TABLE reporty_log_pouziti;
      TRUNCATE TABLE reporty_quick;
      TRUNCATE TABLE shop_nakupy;
      TRUNCATE TABLE shop_nakupy_zrusene;
      TRUNCATE TABLE slevy;
      TRUNCATE TABLE systemove_nastaveni_log;
      TRUNCATE TABLE texty;
      TRUNCATE TABLE ubytovani;
      TRUNCATE TABLE uzivatele_hodnoty;
      TRUNCATE TABLE uzivatele_role;
      TRUNCATE TABLE uzivatele_role_log;
      TRUNCATE TABLE uzivatele_url;

      -- znovu vlozit systemoveho uzivatele
      INSERT INTO `uzivatele_hodnoty` (`id_uzivatele`, `login_uzivatele`, `jmeno_uzivatele`, `prijmeni_uzivatele`, `ulice_a_cp_uzivatele`, `mesto_uzivatele`, `stat_uzivatele`, `psc_uzivatele`, `telefon_uzivatele`, `datum_narozeni`, `heslo_md5`, `funkce_uzivatele`, `email1_uzivatele`, `email2_uzivatele`, `jine_uzivatele`, `mrtvy_mail`, `forum_razeni`, `random`, `zustatek`, `pohlavi`, `registrovan`, `ubytovan_s`, `skola`, `pomoc_typ`, `pomoc_vice`, `op`, `nechce_maily`, `poznamka`, `potvrzeni_zakonneho_zastupce`, `potvrzeni_proti_covid19_pridano_kdy`, `potvrzeni_proti_covid19_overeno_kdy`, `infopult_poznamka`, `typ_dokladu_totoznosti`, `statni_obcanstvi`) 
        VALUES (1, 'SYSTEM', 'SYSTEM', 'SYSTEM', 'SYSTEM', 'SYSTEM', '1', 'SYSTEM', 'SYSTEM', '2023-01-27', '', '0', 'system@gamecon.cz', 'system@gamecon.cz', '', '1', '', '2e3012801cdebf6db162', '0', 'm', '2023-01-27 00:00:00', NULL, NULL, '', '', '', '2023-01-27 00:00:00', '', NULL, NULL, NULL, '', '', 'ČR');

      SET FOREIGN_KEY_CHECKS = 1;
      SQL;
    $queries = explode(';', $query); // Split the input query using ";"

    foreach ($queries as $singleQuery) {
      $singleQuery = trim($singleQuery); // Remove leading/trailing spaces
      if (!empty($singleQuery)) {
        dbQuery($singleQuery); // Execute each individual query
      }
    }

    // TODO: oprava výchozích hodnot -> smazat až bude v db
    dbQuery(
      <<<SQL
        ALTER TABLE `uzivatele_hodnoty` 
            CHANGE `funkce_uzivatele` `funkce_uzivatele` TINYINT(4) NOT NULL DEFAULT '0'
          , CHANGE `email2_uzivatele` `email2_uzivatele` varchar(255) COLLATE utf8_czech_ci NOT NULL DEFAULT ''
          , CHANGE `jine_uzivatele` `jine_uzivatele` text COLLATE utf8_czech_ci NOT NULL DEFAULT ''
          , CHANGE `mrtvy_mail` `mrtvy_mail` tinyint(4) NOT NULL DEFAULT '0'
          , CHANGE `forum_razeni` `forum_razeni` varchar(1) COLLATE utf8_czech_ci NOT NULL DEFAULT ''
          , CHANGE `zustatek` `zustatek` INT(11) NOT NULL DEFAULT '0' COMMENT 'zbytek z minulého roku'
          , CHANGE `poznamka` `poznamka` VARCHAR(4096) CHARACTER SET utf8 COLLATE utf8_czech_ci NOT NULL DEFAULT ''
          , CHANGE `pomoc_typ` `pomoc_typ` VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_czech_ci NOT NULL DEFAULT ''
          , CHANGE `pomoc_vice` `pomoc_vice` TEXT CHARACTER SET utf8 COLLATE utf8_czech_ci NOT NULL DEFAULT ''
          ;
      SQL
    );
  }

  public static function vytvorAdmina()
  {
    dbQuery(
      <<<SQL
        INSERT IGNORE INTO `uzivatele_hodnoty` (`id_uzivatele`, `login_uzivatele`, `jmeno_uzivatele`, `prijmeni_uzivatele`, `ulice_a_cp_uzivatele`, `mesto_uzivatele`, `stat_uzivatele`, `psc_uzivatele`, `telefon_uzivatele`, `datum_narozeni`, `heslo_md5`, `funkce_uzivatele`, `email1_uzivatele`, `email2_uzivatele`, `jine_uzivatele`, `nechce_maily`, `mrtvy_mail`, `forum_razeni`, `random`, `zustatek`, `pohlavi`, `registrovan`, `ubytovan_s`, `skola`, `poznamka`, `pomoc_typ`, `pomoc_vice`, `op`) VALUES
          (10000, 'localAdmin', 'local', 'Admin', '', '', 1, '', '', '0001-00-00', '\$2y\$10\$KYv2m7yoT2OBDUYmH3oK4uaeM6wgQECy6/uiSYvJt9yIfAWQRfwi6', 0, 'localAdmin.gamecon.cz', '', '', '2019-05-08 00:00:00', 0, '', '8666b8a380d284add268', 0, 'm', '2019-05-08 00:00:00', NULL, NULL, '', '', '', '')
          ;
      SQL
    );

    dbQuery(
      <<<SQL
        INSERT IGNORE INTO `uzivatele_role` (`id_uzivatele`, `id_role`, `posazen`) VALUES
          (10000, 2, '2019-05-08 15:57:22'),
          (10000, 20, '2019-05-08 15:57:22');
      SQL
    );
  }


  // TODO: lepší pojmenování
  public static function smazDbNakonec($smazDBNameDatabazi = true)
  {
    register_shutdown_function(static function () use ($smazDBNameDatabazi) {
      // nemůžeme použít předchozí $connection, protože to už je uzavřené
      $connection = dbConnectTemporary();
      if ($smazDBNameDatabazi)
        dbQuery(sprintf('DROP DATABASE IF EXISTS `%s`', DB_NAME), null, $connection);
      $dbTestPrefix            = DB_TEST_PREFIX;
      $oldTestDatabasesWrapped = dbFetchAll("SHOW DATABASES LIKE '{$dbTestPrefix}%'", [], $connection);
      foreach ($oldTestDatabasesWrapped as $oldTestDatabaseWrapped) {
        dbQuery(sprintf('DROP DATABASE IF EXISTS `%s`', reset($oldTestDatabaseWrapped)), null, $connection);
      }
    });
  }
}
