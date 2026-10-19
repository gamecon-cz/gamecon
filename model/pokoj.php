<?php

use App\Enum\ProductTagCode;

/**
 * Počítáme s tím, že uživatel bydlí jen na jednom pokoji, jinak se to rozsype.
 * Také nerozlišuje mezi stavy pokoj neexistuje vs nikdo na něm nebydlí
 */
class Pokoj
{

    protected $r;

    protected function __construct($r)
    {
        $this->r = $r;
    }

    /** Vrací číslo pokoje */
    function cislo()
    {
        return $this->r['pokoj'];
    }

    /** Je uživatel $u ubytován na tomto pokoji? */
    function ubytovan(Uzivatel $u)
    {
        //TODO
    }

    /** Vrací iterátor uživatelů ubytovaných na pokoji */
    function ubytovani()
    {
        return Uzivatel::zIds($this->r['ubytovani']);
    }

    /** (Pře)ubytuje uživatele $u na pokoj $cislo, vytvoří pokud je potřeba */
    static function ubytujNaCislo(Uzivatel $u, $cislo)
    {
        $pokoj = trim($cislo);
        $o     = dbQueryS('
      SELECT noc.accommodation_day AS ubytovani_den
      FROM shop_nakupy AS nakupy
      JOIN product_variant AS noc ON noc.id = nakupy.variant_id
      JOIN product_product_tag ON product_product_tag.product_id = noc.product_id
      JOIN product_tag ON product_tag.id = product_product_tag.tag_id AND product_tag.code = $2
      WHERE nakupy.id_uzivatele = $0 AND nakupy.rok = $1
    ', [0 => $u->id(), 1 => ROCNIK, 2 => ProductTagCode::UBYTOVANI->value]);
        if ($o->rowCount() == 0) {
            throw new Chyba('Uživatel nemá ubytování nebo ubytování pro daný den neexistuje');
        }
        dbQueryS('DELETE FROM ubytovani WHERE rok = $2 AND id_uzivatele = $1', [$u->id(), ROCNIK]);
        $valuesSqlArray = [];
        while ($r = $o->fetch(\PDO::FETCH_ASSOC)) {
            $valuesSqlArray[] = '(' . $u->id() . ',' . $r['ubytovani_den'] . ',' . dbQv($pokoj) . ',' . ROCNIK . ')';
        }
        $valuesSql = implode(",\n", $valuesSqlArray);
        $q         = "INSERT INTO ubytovani(id_uzivatele, den, pokoj, rok) VALUES $valuesSql";
        dbQuery($q);
    }

    /** Vrátí letošní pokoj s číslem $cislo */
    static function zCisla($cislo)
    {
        return self::zWhere('WHERE pokoj = $1 AND rok = $2', [$cislo, ROCNIK]);
    }

    /** Vrátí pokoj, kde letos bydlí uživatel $u */
    static function zUzivatele(Uzivatel $u)
    {
        return self::zWhere('WHERE rok = $2 AND pokoj = (SELECT MAX(pokoj) FROM ubytovani WHERE id_uzivatele = $1 AND rok = $2)', [$u->id(), ROCNIK]);
    }

    /** Vrátí iterátor pokojů podle zadané where klauzule */
    protected static function zWhere($where, $params)
    {
        $r = dbOneLine("
      SELECT pokoj, GROUP_CONCAT(DISTINCT id_uzivatele) AS ubytovani
      FROM ubytovani
      $where
      GROUP BY pokoj
    ", $params);
        if ($r)
            return new self($r);
        else
            return null;
    }

}

