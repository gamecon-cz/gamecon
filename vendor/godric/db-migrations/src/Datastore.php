<?php

namespace Godric\DbMigrations;

class Datastore {

    private
        $db,
        $tableName;

    function __construct($db, $tableName) {
        $this->db = $db;
        $this->tableName = $tableName;
    }

    function get($key) {
        $keySql = "'" . $this->db->escape_string($key) . "'";

        try {
            $q = $this->db->query("SELECT value FROM {$this->tableName} WHERE name = {$keySql}");
        } catch(\mysqli_sql_exception $e) {
            if (preg_match("/^Table [^ ]+ doesn't exist/", $e->getMessage()))
                return null;

            throw $e;
        }


        $value = $q->fetch_row()[0] ?? null;
        if ($value === null) return null;

        return unserialize($value);
    }

    function set($key, $value) {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                name  VARCHAR(200) PRIMARY KEY,
                value VARCHAR(5000)
            )
        ");

        $keySql = "'" . $this->db->escape_string($key) . "'";
        $valueSql = "'" . $this->db->escape_string(serialize($value)) . "'";

        $this->db->query("
            INSERT INTO {$this->tableName}
            VALUES ($keySql, $valueSql)
            ON DUPLICATE KEY UPDATE value = $valueSql
        ");
    }

}
