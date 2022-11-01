<?php

/**
 * Extended PDO – databázová knihovna napodobující originální fce
 */
class EPDO extends PDO
{

    /**
     * Vloží do tabulky daného názvu nový řádek definovaný jako asoc. pole
     */
    public function insert($tabulka, $radek) {
        $sloupce = implode(',', array_map([$this, 'qi'], array_keys($radek)));
        $hodnoty = implode(',', array_map([$this, 'qv'], $radek));
        $this->query("INSERT INTO $tabulka ($sloupce) VALUES ($hodnoty)");
    }

    /**
     * Provede dotaz
     * @param string $statement
     * @param int $mode
     * @param mixed $fetch_mode_args
     * @return PDOStatement|false
     * @todo počítání času a podobně
     * @todo argumenty
     * @todo nějaký složitější systém výjimek na jemné ladění
     */
    public function query($statement, $mode = PDO::ATTR_DEFAULT_FETCH_MODE, ...$fetch_mode_args): PDOStatement|false {
        /*
        // inspirace pro argumenty preg style
        $delta = strpos($q, '$0')===false ? -1 : 0; // povolení číslování $1, $2, $3...
        return dbQuery(
          preg_replace_callback('~\$([0-9]+)~', function($m)use($pole,$delta){
            return dbQv($pole[ $m[1] + $delta ]);
          },$q)
        );
        */
        $o = parent::query($statement);
        if ($o === false) {
            var_dump($this->errorInfo());
            throw new Exception($this->errorInfo()[2]);
        }
        return $o;
    }

    /**
     * Quote identifier (with backticks)
     * @todo odladit jestli ten kód (v mysql) funguje
     */
    public function qi($identifier) {
        return "`" . str_replace("`", "``", $identifier) . "`";
    }

    /**
     * Quote value (with apostrophes around)
     */
    public function qv($value) {
        return $this->quote($value);
    }

}
