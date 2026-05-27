<?php

namespace Godric\DbMigrations;

class Migration {

    private
        $db,
        $id,
        $path;

    function __construct(string $path, int $id, \mysqli $db) {
        $this->path = $path;
        $this->id = $id;
        $this->db = $db;
    }

    function apply() {
        require $this->path;
    }

    function getHash() {
        $hash = sha1_file($this->path);
        if ($hash === false) throw new \Exception('Failed to read file.');
        return $hash;
    }

    function getId() {
        return $this->id;
    }

    function q($query) {
        $this->db->multi_query($query);

        $i = 0;
        do {
            $result = $this->db->use_result();
            $i++;
        } while ($this->db->more_results() && $this->db->next_result());

        if ($this->db->error) {
            $i++;
            throw new \Exception("Error in multi_query number $i: {$this->db->error}");
        }

        return $result;
    }

}
