<?php declare(strict_types=1);

/**
 * @method static ObchodMrizka zId($id)
 * @method static ObchodMrizka[] zVsech($id)
 */
class ObchodMrizka extends \DbObject
{
    protected static $tabulka = 'obchod_mrizky';
    protected static $pk = 'id';

    public static function novy($array=null) {
        $obj = new ObchodMrizka(array_replace(["id"=>null, "text"=>null], $array ?? []));
        return $obj;
    }


    public function id($val = null) {
        return intval($this->getSetR('id', $val));
    }
    public function text($val = null) {
        return $this->getSetR('text', $val) ?: "";
    }
}
