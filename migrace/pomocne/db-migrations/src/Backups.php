<?php

namespace Godric\DbMigrations;

use MySQLDump;

/**
 * Class for backup and restore of database to/from file.
 */
class Backups
{

    private $db;
    private $directory;

    public function __construct($db, $directory) {
        $this->db = $db;
        $this->directory = $directory;
    }

    public function backupBefore(Migration $migration) {
        $this->backup("pre-migration-{$migration->getCode()}");
    }

    /**
     * Dump the whole database into a single gzip file named "<name>.sql.gz".
     * The dump is created with mode 0600 — it contains the full DB, so it must
     * not be readable by other accounts on the host.
     */
    public function backup(string $name): void {
        // skip empty db as workaround for MySQLDump bug
        if (empty($this->getTableNames())) {
            return;
        }

        $file = "{$this->directory}/{$name}.sql.gz";
        $dump = new MySQLDump($this->db);
        $dump->save($file);
        chmod($file, 0600);
    }

    public function clearDatabase() {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($this->getTableNames() as $table) {
            $this->db->query("DROP TABLE $table");
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function getTableNames() {
        $dbName = $this->db->query('SELECT DATABASE()')->fetch_row()[0];
        if (!$dbName) {
            throw new \Exception('Could not read DB name.');
        }

        $tables = $this->db->query("
          SELECT table_name
          FROM information_schema.tables
          WHERE table_schema = '$dbName';
        ")->fetch_all();

        return array_map(static function (array $row) {
            return $row[0];
        }, $tables);
    }

}
