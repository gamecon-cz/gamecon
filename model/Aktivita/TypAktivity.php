<?php

namespace Gamecon\Aktivita;

/**
 * Typ aktivit (programová linie)
 *
 * For Doctrine entity equivalent @see \App\Entity\ActivityType
 *
 * @method static TypAktivity|null zId($id, bool $zCache = false)
 * @method static TypAktivity[] zVsech(bool $zCache = false)
 */
class TypAktivity extends \DbObject
{

    public const TURNAJ_V_DESKOVKACH = 1;
    public const LARP                = 2;
    public const PREDNASKA           = 3;
    public const RPG                 = 4;
    public const WORKSHOP            = 5;
    public const WARGAMING           = 6;
    public const BONUS               = 7;
    public const LKD                 = 8; // legendy klubu dobrodruhů
    public const DRD                 = 9; // mistrovství v DrD
    public const EPIC                = 11;
    public const DOPROVODNY_PROGRAM  = 12;
    public const DESKOHERNA          = 13;
    // interní
    public const SYSTEMOVA   = 0;
    public const TECHNICKA   = 10; // účast na tchnické aktivitě => cena aktivity = bonus pro "vypravěče"
    public const BRIGADNICKA = 102; // účast na brigádnícké aktivitě => cena aktivityu = výplata pro "vypravěče" (brigádníka)

    public static function zNazvu(string $nazev): ?TypAktivity
    {
        return static::zWhereRadek(static::$sloupecNazev . ' = ' . dbQv($nazev));
    }

    public static function zUrl($url = null): ?TypAktivity
    {
        if ($url === null) {
            $url = \Url::zAktualni()->cela();
        }
        return self::zWhereRadek('url_typu_mn = $1', [$url]);
    }

    public static function zViditelnych()
    {
        return self::zWhere('zobrazit_v_menu = 1');
    }

    /**
     * @param int|string $idTypuAktivity
     * @return bool
     */
    public static function jeInterniDleId($idTypuAktivity): bool
    {
        return in_array((int)$idTypuAktivity, self::interniTypy());
    }

    /**
     * @return array<int>
     */
    public static function interniTypy(): array
    {
        return [self::TECHNICKA, self::BRIGADNICKA];
    }

    /**
     * @return array<int>
     */
    public static function typyKterymNevadiSdileniMistnostiSZadnymiTypy(): array
    {
        return [self::TECHNICKA, self::BRIGADNICKA];
    }

    /**
     * @return array<int>
     */
    public static function typyKterymNevadiSdileniMistnostiSeStejnymTypem(): array
    {
        return [self::WARGAMING, self::DESKOHERNA];
    }

    protected static $tabulka      = 'akce_typy';
    protected static $pk           = 'id_typu';
    protected static $sloupecNazev = 'typ_1pmn';

    public function id(): int
    {
        return (int)parent::id();
    }

    /** Vrátí popisek bez html a názvu */
    public function bezNazvu()
    {
        return trim(strip_tags(preg_replace(
            '@<h1>[^<]+</h1>@',
            '',
            $this->oTypu(),
            1, // limit
        )));
    }

    public function nazev()
    {
        return $this->r['typ_1pmn'];
    }

    public function nastavNazev(string $novyNazev): void {
        $this->r['typ_1pmn'] = $novyNazev;
    }

    public function nazevJednotnehoCisla()
    {
        return $this->r['typ_1p'];
    }

    public function __toString()
    {
        return (string)$this->nazev();
    }

    /** Název natáhnutý ze stránky */
    public function nazevDlouhy()
    {
        preg_match('@<h1>([^<]+)</h1>@', $this->oTypu(), $m);
        return $m[1];
    }

    public function oTypu()
    {
        $s = \Stranka::zId($this->r['stranka_o']);
        return $s ? $s->html() : null;
    }

    public function popisKratky()
    {
        return $this->r['popis_kratky'];
    }

    public function poradi()
    {
        return $this->r['poradi'];
    }

    public function posilatMailyNedorazivsim()
    {
        return (bool)$this->r['mail_neucast'];
    }

    /** Pole stránek patřících k linii */
    public function stranky()
    {
        return \Stranka::zUrlPrefixu($this->url());
    }

    public function url(): string
    {
        return $this->r['url_typu_mn'];
    }

    public function jeBrigadnicka(): bool
    {
        return $this->id() === self::BRIGADNICKA;
    }

    public function jeInterni(): bool
    {
        return self::jeInterniDleId($this->id());
    }

    public function sdileniMistnostiJeProNiProblem(): bool
    {
        return !in_array(
            $this->id(),
            [
                ...self::typyKterymNevadiSdileniMistnostiSZadnymiTypy(),
                ...self::typyKterymNevadiSdileniMistnostiSeStejnymTypem(),
            ],
        );
    }

    public function nevadiMuSdileniMistnostiSeStejnymTypem(): bool
    {
        return in_array(
            $this->id(),
            self::typyKterymNevadiSdileniMistnostiSeStejnymTypem(),
            true,
        );
    }
}
