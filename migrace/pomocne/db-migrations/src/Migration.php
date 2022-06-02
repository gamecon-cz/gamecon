<?php

namespace Godric\DbMigrations;

class Migration
{

    private $db;
    private $code;
    private $path;

    public function __construct(string $path, string $code, \mysqli $db) {
        $this->path = $path;
        $this->code = removeDiacritics($code);
        $this->db = $db;
    }

    public function apply() {
        require $this->path;
    }

    public function getHash(): string {
        $hash = sha1_file($this->path);
        if ($hash === false) {
            throw new \RuntimeException('Can not read DB migration file ' . $this->path);
        }
        return $hash;
    }

    public function getId(): ?int {
        return $this->getVersion() === 1
            ? (int)$this->getCode()
            : null;
    }

    public function getCode(): string {
        return $this->code;
    }

    public function getVersion(): int {
        return is_numeric($this->code)
            ? 1
            : 2;
    }

    /**
     * @param $query
     * @return false|\mysqli_result
     * @throws \Exception
     */
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
