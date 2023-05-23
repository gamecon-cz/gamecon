<?php

class Url
{

    /** Řekne jestli jde o povolenou URL nebo ne */
    public static function povolena(string $url): bool
    {
        return !str_contains($url, '/.')
            && preg_match('@^[a-zA-Z0-9][A-Za-z0-9\-/.]*$|^$@', $url);
    }

    /**
     * Vrátí aktuální reálnou url
     * @todo zobecnit na $_SERVER nebo podobně
     */
    public static function zAktualni(): self
    {
        if (!self::$aktualni) {
            self::$aktualni = new self('req');
        }
        return self::$aktualni;
    }

    private string $surova;
    private string $cista;
    private array  $casti;
    private static $aktualni;

    /**
     * Konstruktor bere na vstupu řetězec s názvem GET proměnné, z které zkusí
     * vycucnout URL
     */
    public function __construct(string $getName)
    {
        $this->surova = isset($_GET[$getName])
            ? (string)$_GET[$getName]
            : '';
        if (!self::povolena($this->surova)) {
            throw new UrlException("Nepovolené znaky v URL '{$this->surova}'");
        }
        $this->cista = $this->surova;
        $this->casti = $this->cista !== ''
            ? explode('/', $this->cista)
            : [];
    }

    /** Vrací část url na daném pořadím (od 0) */
    public function cast($i)
    {
        return $this->casti[$i] ?? null;
    }

    /** Vrací celou url */
    public function cela()
    {
        return $this->cista;
    }

    /** Vrací počet zadaných částí url */
    public function delka(): int
    {
        return count($this->casti ?? []);
    }

}

/**
 * Výjimky pro chyby v url
 */
class UrlException extends Exception
{
}
