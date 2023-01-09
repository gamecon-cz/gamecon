<?php

namespace Gamecon\Vyjimkovac;

use Gamecon\Kanaly\GcMail;

class VyjimkovacChyba
{
    public const VYJIMKA = 'vyjimka';

    private array $radek;
    private ?string $idPosledniUlozeneChyby = null;

    public static function absolutniUrlDetailuChyby(int $idChyby, string $urlAdmin = URL_ADMIN): string {
        return $urlAdmin . self::urlDetailuChyby($idChyby);
    }

    public static function urlDetailuChyby(int $idChyby): string {
        return '/web/chyby?' . self::VYJIMKA . '=' . $idChyby;
    }

    public static function zVyjimky(\Throwable $throwable) {
        $radek = self::radekInit();
        try {
            $serialized = serialize($throwable);
        } catch (\Throwable $serializeError) {
            $serialized = $throwable->getTraceAsString();
        }
        $radek = array_merge($radek, [
            'jazyk'     => 'php',
            'radek'     => $throwable->getLine(),
            'soubor'    => $throwable->getFile(),
            'typ'       => get_class($throwable),
            'zavaznost' => 3,
            'zprava'    => $throwable->getMessage(),
            'vyjimka'   => base64_encode($serialized),
        ]);
        if ($throwable instanceof \DbException) {
            $radek['data'] = trim($throwable->getTrace()[0]['args'][0] ?? null);
        }
        if ($throwable instanceof JsException) {
            $radek['url']       = $_SERVER['HTTP_REFERER'] ?? null;
            $radek['zavaznost'] = 2;
            $radek['jazyk']     = 'js';
        }
        if ($throwable instanceof \ErrorException) {
            $s                  = $throwable->getSeverity();
            $radek['typ']       = self::typHr($s);
            $radek['zavaznost'] = 1;
            if ($s & E_WARNING) {
                $radek['zavaznost'] = 2;
            }
            if ($s & E_ERROR) {
                $radek['zavaznost'] = 4;
            }
        }
        return new self($radek);
    }

    /**
     * Vrátí pole s řádkem inicializované z glob. proměnných (čas vytvoření, url,
     * ...)
     */
    private static function radekInit(): array {
        $radek = [
            'vznikla' => time(),
            'url'     => 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
            'zdroj'   => $_SERVER['HTTP_REFERER'] ?? null,
        ];

        try {
            if ($u = \Uzivatel::zSession()) { // selze pokud uz session jeste nebezi a na vystupu uz je nejaky outpout
                $radek['uzivatel'] = $u->id();
            }
        } catch (\Throwable $throwable) {
            // nothing to do with that here...
        }

        return $radek;
    }

    /** Vrátí čitelný formát pro zadaný číselný typ chyby */
    private static function typHr(int $severity) {
        $return = "";
        if ($severity & E_ERROR) // 1 //
            $return .= '& E_ERROR ';
        if ($severity & E_WARNING) // 2 //
            $return .= '& E_WARNING ';
        if ($severity & E_PARSE) // 4 //
            $return .= '& E_PARSE ';
        if ($severity & E_NOTICE) // 8 //
            $return .= '& E_NOTICE ';
        if ($severity & E_CORE_ERROR) // 16 //
            $return .= '& E_CORE_ERROR ';
        if ($severity & E_CORE_WARNING) // 32 //
            $return .= '& E_CORE_WARNING ';
        if ($severity & E_COMPILE_ERROR) // 64 //
            $return .= '& E_COMPILE_ERROR ';
        if ($severity & E_COMPILE_WARNING) // 128 //
            $return .= '& E_COMPILE_WARNING ';
        if ($severity & E_USER_ERROR) // 256 //
            $return .= '& E_USER_ERROR ';
        if ($severity & E_USER_WARNING) // 512 //
            $return .= '& E_USER_WARNING ';
        if ($severity & E_USER_NOTICE) // 1024 //
            $return .= '& E_USER_NOTICE ';
        if ($severity & E_STRICT) // 2048 //
            $return .= '& E_STRICT ';
        if ($severity & E_RECOVERABLE_ERROR) // 4096 //
            $return .= '& E_RECOVERABLE_ERROR ';
        if ($severity & E_DEPRECATED) // 8192 //
            $return .= '& E_DEPRECATED ';
        if ($severity & E_USER_DEPRECATED) // 16384 //
            $return .= '& E_USER_DEPRECATED ';
        return substr($return, 2);
    }

    protected function __construct(array $radek) {
        $this->radek = $radek;
    }

    public function __call(string $metoda, $args) {
        if (isset($this->radek[$metoda]) && $args === []) {
            return $this->radek[$metoda];
        }
        throw new \BadMethodCallException($metoda);
    }

    public function uloz(\EPDO $db): self {
        if ($this->radek) {
            $this->zajistiTabulkyProChyby($db);
            $db->insert('chyby', $this->radek);
            $this->idPosledniUlozeneChyby = $db->lastInsertId();
        }
        return $this;
    }

    private function zajistiTabulkyProChyby(\EPDO $db) {
        $db->query('CREATE TABLE IF NOT EXISTS chyby(
            jazyk     TEXT,
            typ       TEXT,
            zprava    TEXT,
            soubor    TEXT,
            radek     INTEGER,

            zavaznost INTEGER,
            vznikla   INTEGER,
            url       TEXT,
            zdroj     TEXT,
            uzivatel  INTEGER,
            data      TEXT,
            vyjimka   TEXT
        )');
    }

    public function odesli(array $emails) {
        if ($emails) {
            (new GcMail())
                ->adresati($emails)
                ->predmet("Gamecon chyba: {$this->radek['jazyk']}, typ {$this->radek['typ']}")
                ->text($this->radek['zprava'] . "\r\n\r\n<a href='" . URL_ADMIN . "/web/chyby/?" . self::VYJIMKA . "={$this->idPosledniUlozeneChyby}'>Detail</a>")
                ->odeslat();
        }
    }

}
