<?php
namespace Godric\DbMigrations;

use MySQLDump;

/**
 * Class for backup and restore of database to/from file.
 */
class Backups {

    private
        $db,
        $directory;

    function __construct($db, $directory) {
        $this->db = $db;
        $this->directory = $directory;
    }

    function backupBefore(Migration $migration) {
        // skip empty db as workaround for MySQLDump bug
        if (empty($this->getTableNames()))
            return;

        $dump = new MySQLDump($this->db);
        $id = sprintf('%03d', $migration->getId());
        $dump->save("{$this->directory}/pre-{$id}.sql");
    }

    function clearDatabase() {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($this->getTableNames() as $table) {
            $this->db->query("DROP TABLE $table");
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function getTableNames() {
        $dbName = $this->db->query('SELECT DATABASE()')->fetch_row()[0];
        if (!$dbName) throw new \Exception('Could not read DB name.');

        $tables = $this->db->query("
          SELECT table_name
          FROM information_schema.tables
          WHERE table_schema = '$dbName';
        ")->fetch_all();

        return array_map(function($r) {
            return $r[0];
        }, $tables);
    }

}
