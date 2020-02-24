<?php

class Stav extends DbObject
{
  public const NOVA = 0; // v přípravě
  public const AKTIVOVANA = 1;
  public const PROBEHNUTA = 2;
  public const SYSTEMOVA = 3; // deprecated
  public const PUBLIKOVANA = 4; // videtelná, nepřihlašovatelá
  public const PRIPRAVENA = 5;

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
}
