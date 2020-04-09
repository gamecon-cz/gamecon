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
  public const PUBLIKOVANA = 4; // videtelná, nepřihlašovatelá
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
}
