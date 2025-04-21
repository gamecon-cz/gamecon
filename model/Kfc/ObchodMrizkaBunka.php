<?php

declare(strict_types=1);

namespace Gamecon\Kfc;

/**
 * @method static ObchodMrizkaBunka|null zId($id, bool $zCache = false)
 * @method static ObchodMrizkaBunka[] zVsech(bool $zCache = false)
 */
class ObchodMrizkaBunka extends \DbObject
{
    protected static $tabulka = 'obchod_bunky';
    protected static $pk      = 'id';

    public static function novy($array = null)
    {
        dbInsertUpdate(static::$tabulka, array_replace(["id" => null, "text" => null], $array ?? []));
        $id = empty($array['id'])
            ? dbInsertId()
            : $array['id'];
        return static::zId($id);
    }

    public const TYP_PREDMET = 0;
    public const TYP_STRANKA = 1;
    public const TYP_ZPET    = 2;
    public const TYP_SHRNUTI = 3;

    public function id($val = null)
    {
        return intval($this->getSetR('id', $val));
    }

    public function typ($val = null)
    {
        return intval($this->getSetR('typ', $val));
    }

    public function text($val = null)
    {
        return $this->getSetR('text', $val) ?: "";
    }

    public function barva($val = null)
    {
        return $this->getSetR('barva', $val);
    }

    public function barvaText($val = null)
    {
        return $this->getSetR('barva_text', $val);
    }

    public function cilId($val = null)
    {
        return intvalOrNull($this->getSetR('cil_id', $val));
    }

    public function mrizkaId($val = null)
    {
        return intvalOrNull($this->getSetR('mrizka_id', $val));
    }

}
