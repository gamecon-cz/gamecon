<?php

/**
 * @method static Lokace | null zId($id)
 * @method static Lokace[] zVsech()
 */
class Lokace extends DbObject
{

  protected static $tabulka = 'akce_lokace';
  protected static $pk = 'id_lokace';

  function __toString() {
    return $this->r['nazev'] . ', ' . $this->r['dvere'];
  }

  function nazev() {
    return $this->r['nazev'];
  }

  function dvere() {
    return $this->r['dvere'];
  }

  function poznamka() {
    return $this->r['poznamka'];
  }

  function poradi(): int {
    return (int)$this->r['poradi'];
  }

  function rok(): int {
    return (int)$this->r['rok'];
  }

  public function id(): int {
    return (int)parent::id();
  }
}
