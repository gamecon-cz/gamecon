<?php

/**
 * Tag aktivity
 * @method static Tag|null zId($id)
 * @method static Tag[] zVsech()
 */
class Tag extends DbObject
{

  public static function zNazvu(string $nazev): ?Tag {
    return static::zWhereRadek(static::$sloupecNazev . ' = ' . dbQv($nazev));
  }

  protected static $tabulka = 'sjednocene_tagy';
  protected static $pk = 'id';
  protected static $sloupecNazev = 'nazev';

  public function id(): int {
    return (int)parent::id();
  }

  public function nazev(): string {
    return $this->r[static::$sloupecNazev];
  }
}
