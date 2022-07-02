<?php declare(strict_types=1);

// TODO: do pomocných funkcí
function intvalOrNull($val) {
    return $val == null ? null : intval($val);
}

/**
 * @method static ObchodMrizkaBunka zId($id)
 * @method static ObchodMrizkaBunka[] zVsech($id)
 */
class ObchodMrizkaBunka extends \DbObject
{
    protected static $tabulka = 'obchod_bunky';
    protected static $pk = 'id';

    public function id($val = null) {
        return intval($this->getSetR('id', $val));
    }
    public function typ($val = null) {
        return intval($this->getSetR('typ', $val));
    }
    public function text($val = null) {
        return $this->getSetR('text', $val) ?: "";
    }
    public function barva($val = null) {
        return $this->getSetR('barva', $val);
    }
    public function cil_id($val = null) {
        return intvalOrNull($this->getSetR('cil_id', $val));
    }
    public function mrizka_id($val = null) {
        return intvalOrNull($this->getSetR('mrizka_id', $val));
    }

}
