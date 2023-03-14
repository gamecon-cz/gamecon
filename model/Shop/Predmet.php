<?php declare(strict_types=1);

namespace Gamecon\Shop;

use Gamecon\Shop\PredmetSqlStruktura as SQL;

/**
 * @method static Predmet zId($id)
 */
class Predmet extends \DbObject
{
    protected static $tabulka = 'shop_predmety';
    protected static $pk = 'id_predmetu';

    public function kusuVyrobeno(int $kusuVyrobeno = null): int {
        if ($kusuVyrobeno !== null) {
            $this->r['kusu_vyrobeno'] = $kusuVyrobeno;
        }
        return (int)$this->r['kusu_vyrobeno'];
    }

    public function nazev(): ?string {
        return $this->r[SQL::NAZEV];
    }
}
