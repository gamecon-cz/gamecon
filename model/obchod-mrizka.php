<?php

declare(strict_types=1);

/**
 * @method static ObchodMrizka zId($id)
 * @method static ObchodMrizka[] zVsech()
 */
class ObchodMrizka extends \DbObject
{
    protected static $tabulka = 'obchod_mrizky';
    protected static $pk      = 'id';

    // TODO: mohlo by být součástí DbObject
    public static function novy($array = null)
    {
        dbInsertUpdate(static::$tabulka, array_replace(["id" => null, "text" => null], $array ?? []));
        $id = empty($array['id'])
            ? dbInsertId()
            : $array['id'];
        return static::zId($id);
    }

    public function id($val = null): int
    {
        return (int)($this->getSetR('id', $val));
    }

    public function text($val = null): string
    {
        return $this->getSetR('text', $val) ?: "";
    }
}
