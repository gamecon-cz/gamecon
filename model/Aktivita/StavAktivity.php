<?php

namespace Gamecon\Aktivita;

/**
 * For Doctrine entity equivalent @see \App\Entity\ActivityState
 *
 * @method static StavAktivity|null zId($id, bool $zCache = false)
 * @method static StavAktivity[] zVsech(bool $zCache = false)
 */
class StavAktivity extends \DbObject
{
    // 0 dělá potíže při kopírování do klonu tabulky - INSERT 0 do AUTOINCREMENT sloupce se chová jako NULL
    public const NOVA = 1; // v přípravě, neveřejná
    public const PUBLIKOVANA = 5; // viditelná, nepřihlašovatelá
    public const PRIPRAVENA  = 6; // viditelná, nepřihlašovatená, připravená k automatické aktivaci
    public const AKTIVOVANA  = 2; // viditelná a otevřená pro přihlašování
    public const UZAVRENA    = 3;
    public const ZAMCENA     = 7;
    public const SYSTEMOVA   = 4;

    public static function jeZnamy(int $stav): bool
    {
        return in_array($stav, self::vsechnyStavy(), true);
    }

    /**
     * @return int[]
     */
    public static function vsechnyStavy(): array
    {
        return [
            self::NOVA,
            self::AKTIVOVANA,
            self::UZAVRENA,
            self::SYSTEMOVA,
            self::PUBLIKOVANA,
            self::PRIPRAVENA,
            self::ZAMCENA,
        ];
    }

    /**
     * @return int[]
     */
    public static function bezneViditelneStavy(): array
    {
        return [
            self::AKTIVOVANA,
            self::PUBLIKOVANA,
            self::PRIPRAVENA,
            self::ZAMCENA,
            self::UZAVRENA,
        ];
    }

    /**
     * @return int[]
     */
    public static function probehnuteStavy(): array
    {
        return [self::ZAMCENA, self::UZAVRENA];
    }

    public static function dejNazev(int $stav): string
    {
        switch ($stav) {
            case self::NOVA :
                return 'nová';
            case self::AKTIVOVANA :
                return 'aktivovaná';
            case self::UZAVRENA :
                return 'uzavřená';
            case self::PUBLIKOVANA :
                return 'publikovaná';
            case self::PRIPRAVENA :
                return 'připravená';
            case self::ZAMCENA :
                return 'zamčená';
            case self::SYSTEMOVA :
                return 'systémová';
            default :
                throw new \LogicException('Neznámý stav aktivity: ' . $stav);
        }
    }

    protected static $tabulka = 'akce_stav';
    protected static $pk      = 'id_stav';

    public static function dejPrazdny(): self
    {
        return new static([]);
    }

    function __toString()
    {
        return $this->nazev();
    }

    public function id(): int
    {
        return (int)parent::id();
    }

    function nazev(): string
    {
        return (string)$this->r['nazev'];
    }

    public function jeNanejvysPripravenaKAktivaci(): bool
    {
        return in_array($this->id(), [self::NOVA, self::PUBLIKOVANA, self::PRIPRAVENA], true);
    }

    public function jeNova(): bool
    {
        return $this->id() === self::NOVA;
    }

    public function jeAktivovana(): bool
    {
        return $this->id() === self::AKTIVOVANA;
    }

    public function jeUzavrena(): bool
    {
        return $this->id() === self::UZAVRENA;
    }

    public function jeSystemova(): bool
    {
        return $this->id() === self::SYSTEMOVA;
    }

    public function jePublikovana(): bool
    {
        return $this->id() === self::PUBLIKOVANA;
    }

    public function jePripravenaKAktivaci(): bool
    {
        return $this->id() === self::PRIPRAVENA;
    }

    public function jeZamcena(): bool
    {
        return $this->id() === self::ZAMCENA;
    }
}
