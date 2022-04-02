<?php

class Url
{

    private $surova;
    private $cista;
    private $casti;
    private static $aktualni;

    /**
     * Konstruktor bere na vstupu řetězec s názvem GET proměnné, z které zkusí
     * vycucnout URL
     */
    public function __construct($getName) {
        $this->surova = isset($_GET[$getName])
            ? $_GET[$getName]
            : '';
        if (!self::povolena($this->surova)) {
            throw new UrlException('Nepovolené znaky v URL.');
        } else {
            $this->cista = $this->surova;
        }
        $this->casti = explode('/', $this->cista);
    }

    /** Vrací část url na daném pořadím (od 0) */
    public function cast($i) {
        return $this->casti[$i] ?? null;
    }

    /** Vrací celou url */
    public function cela() {
        return $this->cista;
    }

    /** Vrací počet zadaných částí url */
    public function delka() {
        return count($this->casti ?? []);
    }

    /** Řekne jestli jde o povolenou URL nebo ne */
    static function povolena($url) {
        return strpos($url, '/.') === false
            && preg_match('@^[a-zA-Z0-9][A-Za-z0-9\-/\.]*$|^$@', $url);
    }

    /**
     * Vrátí aktuální reálnou url
     * @todo zobecnit na $_SERVER nebo podobně
     */
    static function zAktualni() {
        if (!self::$aktualni) {
            self::$aktualni = new self('req');
        }
        return self::$aktualni;
    }

}

/**
 * Výjimky pro chyby v url
 */
class UrlException extends Exception
{
}
