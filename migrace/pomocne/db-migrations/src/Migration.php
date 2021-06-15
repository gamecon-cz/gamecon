<?php

namespace Godric\DbMigrations;

class Migration
{

    private $db;
    private $code;
    private $path;

    public function __construct(string $path, string $code, \mysqli $db) {
        $this->path = $path;
        $this->code = $code;
        $this->db = $db;
    }

    public function apply() {
        require $this->path;
    }

    public function getHash() {
        $hash = sha1_file($this->path);
        if ($hash === false) {
            throw new \Exception('Failed to read file.');
        }
        return $hash;
    }

    public function getId() {
        return $this->getCode();
    }

    public function getCode() {
        return $this->code;
    }

    public function q($query) {
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
