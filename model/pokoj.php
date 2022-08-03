<?php

/**
 * Počítáme s tím, že uživatel bydlí jen na jednom pokoji, jinak se to rozsype.
 * Také nerozlišuje mezi stavy pokoj neexistuje vs nikdo na něm nebydlí
 */
class Pokoj
{

    protected $r;

    protected function __construct($r) {
        $this->r = $r;
    }

    /** Vrací číslo pokoje */
    function cislo() {
        return $this->r['pokoj'];
    }

    /** Je uživatel $u ubytován na tomto pokoji? */
    function ubytovan(Uzivatel $u) {
        //TODO
    }

    /** Vrací iterátor uživatelů ubytovaných na pokoji */
    function ubytovani() {
        return Uzivatel::zIds($this->r['ubytovani']);
    }

    /** (Pře)ubytuje uživatele $u na pokoj $cislo, vytvoří pokud je potřeba */
    static function ubytujNaCislo(Uzivatel $u, $cislo) {
        $pokoj = trim($cislo);
        $o     = dbQueryS('
      SELECT ubytovani_den
      FROM shop_nakupy n
      JOIN shop_predmety p USING(id_predmetu)
      WHERE n.id_uzivatele = $0 AND n.rok = $1 AND p.typ = $2
    ', [0 => $u->id(), 1 => ROK, 2 => \Gamecon\Shop\TypPredmetu::UBYTOVANI]);
        if (mysqli_num_rows($o) == 0) {
            throw new Chyba('Uživatel nemá ubytování nebo ubytování pro daný den neexistuje');
        }
        dbQueryS('DELETE FROM ubytovani WHERE rok = $2 AND id_uzivatele = $1', [$u->id(), ROK]);
        $valuesSqlArray = [];
        while ($r = mysqli_fetch_assoc($o)) {
            $valuesSqlArray[] = '(' . $u->id() . ',' . $r['ubytovani_den'] . ',' . dbQv($pokoj) . ',' . ROK . ')';
        }
        $valuesSql = implode(",\n", $valuesSqlArray);
        $q         = "INSERT INTO ubytovani(id_uzivatele, den, pokoj, rok) VALUES $valuesSql";
        dbQuery($q);
    }

    /** Vrátí letošní pokoj s číslem $cislo */
    static function zCisla($cislo) {
        return self::zWhere('WHERE pokoj = $1 AND rok = $2', [$cislo, ROK]);
    }

    /** Vrátí pokoj, kde letos bydlí uživatel $u */
    static function zUzivatele(Uzivatel $u) {
        return self::zWhere('WHERE rok = $2 AND pokoj = (SELECT MAX(pokoj) FROM ubytovani WHERE id_uzivatele = $1 AND rok = $2)', [$u->id(), ROK]);
    }

    /** Vrátí iterátor pokojů podle zadané where klauzule */
    protected static function zWhere($where, $params) {
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

