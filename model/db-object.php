<?php
if (!defined('DB_NULL')) {
    define('DB_NULL', uniqid('DB_NULL_', true));
}

/**
 * Abstrakce jednoduché třídy nad tabulkou databáze
 */
abstract class DbObject
{

    protected $r; // řádek z databáze

    protected static $tabulka;    // název tabulky - odděděná třída _musí_ přepsat
    protected static $pk = 'id';  // název primárního klíče - odděděná třída může přepsat

    /**
     * Vytvoří objekt na základě řádku z databáze
     * @param array $r
     */
    protected function __construct(array $r) {
        $this->r = $r;
    }

    /**
     * Vrací dbrow pokud je hodnota přítomna nastaví na ni dbrow.
     * Pro nastavení hodnoty na null je potřeba předat DB_NULL
     */
    protected function getSetR($name, $val = null) {
        if ($val != null) {
            $this->r[$name] = $val == DB_NULL
                ? null
                : $val;
        }
        return $this->r[$name];
    }

    /**
     * Vrací dotaz z kterého se načítají objekty. Jako where podmínka se použije
     * parametr $where.
     * Protected, aby odděděná třída mohla v případě nepřepsání použít tuto metodu
     * v static kontextu. Má parametr $where, aby odděděná třída mohla před i za
     * přidávat bižuterii typu join, groupy, řazení, ...
     */
    protected static function dotaz($where) {
        return 'SELECT * FROM ' . static::$tabulka . ' WHERE ' . $where;
    }

    /** Vrátí formulář pro editaci objektu s daným ID nebo pro přidání nového */
    public static function form($id = null) {
        $form = new DbFormGc(static::$tabulka);
        if ($id) {
            $object = self::zId($id);
            if ($object) {
                $form->loadRow($object->r);
            }
        }
        return $form;
    }

    public function uloz(): int {
        $form = new DbFormGc(static::$tabulka);
        $form->loadRow($this->r);
        $form->save();
        return $form->lastSaveChangesCount();
    }

    /** Vrátí ID objektu (hodnota primárního klíče) */
    public function id() {
        return $this->r[static::$pk];
    }

    /** Načte a vrátí objekt s daným ID nebo null */
    public static function zId($id) {
        return self::zWhereRadek(static::$pk . ' = ' . dbQv($id));
    }

    /**
     * Načte a vrátí pole objektů s danými ID (může být prázdné)
     * @param array $ids pole čísel nebo řetězec čísel oddělených čárkami
     */
    public static function zIds($ids) {
        if (is_array($ids)) {
            if (empty($ids)) {
                return [];
            } // vůbec se nedotazovat DB
            return self::zWhere(static::$pk . ' IN (' . dbQa($ids) . ')');
        } else if (preg_match('@^([0-9]+,)*[0-9]+$@', $ids)) {
            return self::zWhere(static::$pk . ' IN (' . $ids . ')');
        } else if ($ids === '') {
            return [];
        } else {
            throw new InvalidArgumentException('Argument musí být pole čísel nebo řetězec čísel oddělených čárkou');
        }
        // TODO co když $ids === null?
    }

    /** Načte a vrátí všechny objekt z databáze */
    public static function zVsech(): array {
        return self::zWhere('1');
    }

    /** Načte a vrátí objekty pomocí dané where klauzule */
    protected static function zWhere($where, $params = null): array {
        $o = dbQuery(static::dotaz($where), $params); // static aby odděděná třída mohla přepsat dotaz na něco složitějšího
        $a = [];
        while ($r = mysqli_fetch_assoc($o)) {
            $a[] = new static($r); // static aby vznikaly objekty správné třídy
        }
        // TODO id jako klíč pole?
        // TODO cacheování?
        return $a;
    }

    /**
     * Načte a vrátí objekt vyhovující where klauzuli nebo null
     * @throws RuntimeException pokud se načte více řádků
     */
    protected static function zWhereRadek($where, $params = null) {
        $a = self::zWhere($where, $params);
        if (count($a) === 1) {
            return $a[0];
        }
        if (!$a) {
            return null;
        }
        throw new RuntimeException(sprintf("Více jak jeden řádek (%d) odpovídá where klauzuli '%s'", count($a), $where));
    }

}
