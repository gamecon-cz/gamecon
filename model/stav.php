<?php

/**
 * @method static Stav zId($id)
 * @method static Stav[] zVsech()
 */
class Stav extends DbObject
{
  public const NOVA = 0; // v přípravě
  public const AKTIVOVANA = 1;
  public const PROBEHNUTA = 2;
  public const SYSTEMOVA = 3; // deprecated
  public const PUBLIKOVANA = 4; // viditelná, nepřihlašovatelá
  public const PRIPRAVENA = 5;

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
      self::PROBEHNUTA,
      self::SYSTEMOVA,
      self::PUBLIKOVANA,
      self::PRIPRAVENA,
    ];
  }

  protected static $tabulka = 'akce_stav';
  protected static $pk = 'id';

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

  public function jeProbehnuta(): bool {
    return $this->id() === self::PROBEHNUTA;
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
}
