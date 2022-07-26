<?php

namespace Gamecon\Aktivita;

/**
 * @method static StavAktivity zId($id)
 * @method static StavAktivity[] zVsech()
 */
class StavAktivity extends \DbObject
{
    public const NOVA = 0; // v přípravě
    public const AKTIVOVANA = 1;
    public const UZAVRENA = 2;
    public const SYSTEMOVA = 3; // deprecated
    public const PUBLIKOVANA = 4; // viditelná, nepřihlašovatelá
    public const PRIPRAVENA = 5;
    public const ZAMCENA = 6;

    public static function jeZnamy(int $stav): bool {
        return in_array($stav, self::vsechnyStavy(), true);
    }

    /**
     * @return int[]
     */
    public static function vsechnyStavy(): array {
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

    public static function dejNazev(int $stav): string {
        switch ($stav) {
            case self::NOVA :
                return 'nová';
            case self::AKTIVOVANA :
                return 'aktivovaná';
            case self::UZAVRENA :
                return 'uzavřená';
            case self::SYSTEMOVA :
                return 'systémová';
            case self::PUBLIKOVANA :
                return 'publikovaná';
            case self::PRIPRAVENA :
                return 'připravená';
            case self::ZAMCENA :
                return 'zamčená';
            default :
                throw new \LogicException('Neznámý stav aktivity: ' . $stav);
        }
    }

    protected static $tabulka = 'akce_stav';
    protected static $pk = 'id_stav';

    public static function dejPrazdny(): self {
        return new static([]);
    }

    function __toString() {
        return $this->nazev();
    }

    public function id(): int {
        return (int)parent::id();
    }

    function nazev(): string {
        return (string)$this->r['nazev'];
    }

    public function jeNanejvysPripravenaKAktivaci(): bool {
        return in_array($this->id(), [self::NOVA, self::PUBLIKOVANA, self::PRIPRAVENA], true);
    }

    public function jeNova(): bool {
        return $this->id() === self::NOVA;
    }

    public function jeAktivovana(): bool {
        return $this->id() === self::AKTIVOVANA;
    }

    public function jeUzavrena(): bool {
        return $this->id() === self::UZAVRENA;
    }

    public function jeSystemova(): bool {
        return $this->id() === self::SYSTEMOVA;
    }

    public function jePublikovana(): bool {
        return $this->id() === self::PUBLIKOVANA;
    }

    public function jePripravenaKAktivaci(): bool {
        return $this->id() === self::PRIPRAVENA;
    }

    public function jeZamcena(): bool {
        return $this->id() === self::ZAMCENA;
    }
}
