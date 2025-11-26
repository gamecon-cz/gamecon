<?php
declare(strict_types=1);

namespace Gamecon\Shop;

use Gamecon\Shop\SqlStruktura\PredmetSqlStruktura as Sql;
use Gamecon\Uzivatel\Dto\PolozkaProBfgr;

/**
 * For Doctrine entity equivalent @see \App\Entity\ShopItem
 *
 * @method static Predmet|null zId($id, bool $zCache = false)
 */
class Predmet extends \DbObject
{
    protected static $tabulka         = Sql::SHOP_PREDMETY_TABULKA;
    protected static $pk              = Sql::ID_PREDMETU;
    protected static $letosniPredmety = [];

    public static function jeToVstupneVcas(
        int    $typPredmetu,
        string $kodPredmetu,
    ): bool {
        return $typPredmetu === TypPredmetu::VSTUPNE && !self::jeToDleCastiKodu($kodPredmetu, 'pozde');
    }

    public static function jeToVstupnePozde(
        int    $typPredmetu,
        string $kodPredmetu,
    ): bool {
        return $typPredmetu === TypPredmetu::VSTUPNE && self::jeToDleCastiKodu($kodPredmetu, 'pozde');
    }

    public static function jeToKostka(string $kodPredmetu): bool
    {
        return self::jeToDleCastiKodu($kodPredmetu, 'kostka');
    }

    public static function jeToNicknack(string $kodPredmetu): bool
    {
        return self::jeToDleCastiKodu($kodPredmetu, 'nicknack');
    }

    public static function jeToBlok(string $kodPredmetu): bool
    {
        return self::jeToDleCastiKodu($kodPredmetu, 'blok');
    }

    public static function jeToPonozka(string $kodPredmetu): bool
    {
        return self::jeToDleCastiKodu($kodPredmetu, 'ponozk');
    }

    public static function jeToPlacka(string $kodPredmetu): bool
    {
        return self::jeToDleCastiKodu($kodPredmetu, 'placka');
    }

    public static function jeToTaska(string $kodPredmetu): bool
    {
        return self::jeToDleCastiKodu($kodPredmetu, 'taska');
    }

    public static function jeToSnidane(string $kodPredmetu): bool
    {
        return self::jeToDleCastiKodu($kodPredmetu, 'snidane');
    }

    public static function jeToObed(string $kodPredmetu): bool
    {
        return self::jeToDleCastiKodu($kodPredmetu, 'obed');
    }

    public static function jeToVecere(string $kodPredmetu): bool
    {
        return self::jeToDleCastiKodu($kodPredmetu, 'vecere');
    }

    /**
     * Pozor, název, ne kód předmětu
     */
    public static function jeToModre(string|PolozkaProBfgr $nazev): bool
    {
        if ($nazev instanceof PolozkaProBfgr) {
            $nazev = $nazev->nazev;
        }
        return self::jeToDleCasti($nazev, 'modr');
    }

    /**
     * Pozor, název, ne kód předmětu
     */
    public static function jeToCervene(string|PolozkaProBfgr $nazev): bool
    {
        if ($nazev instanceof PolozkaProBfgr) {
            $nazev = $nazev->nazev;
        }
        return self::jeToDleCasti($nazev, 'červen');
    }

    public static function jeToTricko(
        string $kodPredmetu,
        int    $typ,
    ): bool {
        return $typ === TypPredmetu::TRICKO && self::jeToDleCastiKodu($kodPredmetu, 'tricko');
    }

    public static function jeToTilko(
        string $kodPredmetu,
        int    $typ,
    ): bool {
        return $typ === TypPredmetu::TRICKO && self::jeToDleCastiKodu($kodPredmetu, 'tilko');
    }

    public static function letosniKostka(int $rocnik): ?static
    {
        return self::letosniPredmet('kostka', $rocnik);
    }

    public static function letosniPlacka(int $rocnik): ?static
    {
        return self::letosniPredmet('placka', $rocnik);
    }

    private static function jeToDleCastiKodu(
        string $cele,
        string $cast,
    ): bool {
        assert(preg_match('~^[a-zA-Z0-9_-]+$~', $cele), sprintf("Kód není validní: '%s'", $cele));
        assert(preg_match('~^[a-zA-Z0-9_-]+$~', $cast), sprintf("Hledaná část není validní kód: '%s'", $cast));

        return self::jeToDleCasti($cele, $cast);
    }

    private static function jeToDleCasti(
        string $cele,
        string $cast,
    ): bool {
        return mb_stripos($cele, $cast) !== false;
    }

    private static function letosniPredmet(
        string $castKodu,
        int    $rocnik,
    ): ?static {
        if (!array_key_exists($castKodu, self::$letosniPredmety)) {
            $typPredmet                       = TypPredmetu::PREDMET;
            $castKoduSql                      = dbQRaw($castKodu);
            $letosniPredmetId                 = dbFetchSingle(<<<SQL
SELECT id_predmetu
FROM shop_predmety
WHERE
    -- letošní je ten, která má nejnovější model a v dřívějších letech si ho nikdo neobjednal
    NOT EXISTS(SELECT * FROM shop_nakupy WHERE shop_nakupy.id_predmetu = shop_predmety.id_predmetu AND shop_nakupy.rok < {$rocnik})
    AND typ = {$typPredmet}
    AND kod_predmetu COLLATE utf8_czech_ci LIKE '%{$castKoduSql}%'
ORDER BY model_rok DESC, je_letosni_hlavni DESC, cena_aktualni DESC, id_predmetu /* dříve nahraný má přednost */
LIMIT 1 -- pro jistotu
SQL,
            );
            $letosniPredmet                   = $letosniPredmetId
                ? static::zId((int)$letosniPredmetId, true)
                : null;
            self::$letosniPredmety[$castKodu] = $letosniPredmet;
        }

        return self::$letosniPredmety[$castKodu];
    }

    public function kusuVyrobeno(int $kusuVyrobeno = null): int
    {
        if ($kusuVyrobeno !== null) {
            $this->r['kusu_vyrobeno'] = $kusuVyrobeno;
        }

        return (int)$this->r['kusu_vyrobeno'];
    }

    public function nazev(): string
    {
        return (string)$this->r[Sql::NAZEV];
    }

    public function cenaAktualni(): float
    {
        return (float)$this->r[Sql::CENA_AKTUALNI];
    }

    public function stav(int $stav = null): int
    {
        if ($stav !== null) {
            $this->r[Sql::STAV] = $stav;
        }

        return (int)$this->r[Sql::STAV];
    }

    public function modelRok(): ?int
    {
        if ($this->r[Sql::MODEL_ROK] === null) {
            return null;
        }

        return (int)$this->r[Sql::MODEL_ROK];
    }

    public function typ(): ?int
    {
        if ($this->r[Sql::TYP] === null) {
            return null;
        }

        return (int)$this->r[Sql::TYP];
    }
}
