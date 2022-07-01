<?php declare(strict_types=1);

namespace Gamecon\Obchod;

/**
 * @method static ObchodMrizka zId($id)
 */
class ObchodMrizka extends \DbObject
{
    protected static $tabulka = 'obchod_mrizky';
    protected static $pk = 'id';

    public function kusuVyrobeno(int $kusuVyrobeno = null): int {
        if ($kusuVyrobeno !== null) {
            $this->r['kusu_vyrobeno'] = $kusuVyrobeno;
        }
        return (int)$this->r['kusu_vyrobeno'];
    }
}
