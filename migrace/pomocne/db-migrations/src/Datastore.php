<?php

namespace Godric\DbMigrations;

class Datastore
{

    private $db;
    private $tableName;

    public function __construct($db, string $tableName) {
        $this->db = $db;
        $this->tableName = $tableName;
    }

    public function get($key) {
        $keySql = $this->db->quote($key);

        try {
            $q = $this->db->query("SELECT value FROM {$this->tableName} WHERE name = {$keySql}");
        } catch (\PDOException $e) {
            if (preg_match("/^.*Table [^ ]+ doesn't exist/", $e->getMessage())) {
                return null;
            }

            throw $e;
        }

        $value = $q->fetch(\PDO::FETCH_NUM)[0] ?? null;
        if ($value === null) {
            return null;
        }

        return unserialize($value, ['allowed_classes' => false]);
    }

    public function set($key, $value) {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                name  VARCHAR(200) PRIMARY KEY,
                value VARCHAR(5000)
            )
        ");

        $keySql = $this->db->quote($key);
        $valueSql = $this->db->quote(serialize($value));

        $this->db->query("
            INSERT INTO {$this->tableName}
            VALUES ($keySql, $valueSql)
            ON DUPLICATE KEY UPDATE value = $valueSql
        ");
    }

}
