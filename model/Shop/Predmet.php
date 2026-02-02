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
    protected static $tabulka = Sql::SHOP_PREDMETY_TABULKA;
    protected static $pk = Sql::ID_PREDMETU;

    public static function jeToVstupneVcas(int $typPredmetu, string $kodPredmetu): bool
    {
        return $typPredmetu === TypPredmetu::VSTUPNE && !self::jeToDleCasti($kodPredmetu, 'pozde');
    }

    public static function jeToVstupnePozde(int $typPredmetu, string $kodPredmetu): bool
    {
        return $typPredmetu === TypPredmetu::VSTUPNE && self::jeToDleCasti($kodPredmetu, 'pozde');
    }

    public static function jeToKostka(string $kodPredmetu): bool
    {
        return self::jeToDleCasti($kodPredmetu, 'kostka');
    }

    public static function jeToNicknack(string $kodPredmetu): bool
    {
        return self::jeToDleCasti($kodPredmetu, 'nicknack');
    }

    public static function jeToBlok(string $kodPredmetu): bool
    {
        return self::jeToDleCasti($kodPredmetu, 'blok');
    }

    public static function jeToPonozka(string $kodPredmetu): bool
    {
        return self::jeToDleCasti($kodPredmetu, 'ponozk');
    }

    public static function jeToPlacka(string $kodPredmetu): bool
    {
        return self::jeToDleCasti($kodPredmetu, 'placka');
    }

    public static function jeToTaska(string $kodPredmetu): bool
    {
        return self::jeToDleCasti($kodPredmetu, 'taska');
    }

    public static function jeToSnidane(string $kodPredmetu): bool
    {
        return self::jeToDleCasti($kodPredmetu, 'snidane');
    }

    public static function jeToObed(string $kodPredmetu): bool
    {
        return self::jeToDleCasti($kodPredmetu, 'obed');
    }

    public static function jeToVecere(string $kodPredmetu): bool
    {
        return self::jeToDleCasti($kodPredmetu, 'vecere');
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
        return $typ === TypPredmetu::TRICKO && self::jeToDleCasti($kodPredmetu, 'tricko');
    }

    public static function jeToTilko(
        string $kodPredmetu,
        int    $typ,
    ): bool {
        return $typ === TypPredmetu::TRICKO && self::jeToDleCasti($kodPredmetu, 'tilko');
    }

    private static function jeToDleCasti(
        string $cele,
        string $cast,
    ): bool {
        return mb_stripos($cele, $cast) !== false;
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
