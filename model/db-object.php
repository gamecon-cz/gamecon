<?php
if (!defined('DB_NULL')) {
    define('DB_NULL', uniqid('DB_NULL_', true));
}

/**
 * Abstrakce jednoduché třídy nad tabulkou databáze
 */
abstract class DbObject
{
    protected static         $tabulka;    // název tabulky - odděděná třída _musí_ přepsat
    protected static ?string $aliasTabulky = null; // volitelný alias tabulky pro zWhere
    protected static         $pk           = 'id';  // název primárního klíče - odděděná třída může přepsat
    /** @var array<string, array<int, static>> */
    private static array $objekty = [];
    /** @var array<string, array<int, static>> */
    private static array $objektyZVsech = [];

    /**
     * Vytvoří objekt na základě řádku z databáze
     * @param array $r
     */
    protected function __construct(protected array $r)
    {
        if (!empty($this->r[static::$pk])) {
            self::doCache($this);
        }
    }

    /**
     * Vrací dbrow pokud je hodnota přítomna nastaví na ni dbrow.
     * Pro nastavení hodnoty na null je potřeba předat DB_NULL
     */
    protected function getSetR(
        $name,
        $val = null,
    ) {
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
    protected static function dotaz($where)
    {
        return 'SELECT * FROM ' . static::$tabulka . ' ' . $where;
    }

    /** Vrátí formulář pro editaci objektu s daným ID nebo pro přidání nového */
    public static function form($id = null)
    {
        $form = new DbFormGc(static::$tabulka);
        if ($id) {
            $object = self::zId($id);
            if ($object) {
                $form->loadRow($object->r);
            }
        }

        return $form;
    }

    public function uloz(): int
    {
        $form = new DbFormGc(static::$tabulka);
        $form->loadRow($this->r);
        $form->save();

        return $form->lastSaveChangesCount();
    }

    /** Vrátí ID objektu (hodnota primárního klíče) */
    public function id()
    {
        return $this->r[static::$pk];
    }

    /** Načte a vrátí objekt s daným ID nebo null */
    public static function zId(
        $id,
        bool $zCache = false,
    ) {
        $objekt = null;
        if ($zCache) {
            $objekt = self::zCache($id);
        }
        $objekt = $objekt ?? self::zWhereRadek(static::$pk . ' = ' . dbQv($id));
        if ($objekt && $zCache) {
            self::doCache($objekt);
        }

        return $objekt;
    }

    private static function zCache($id): ?static
    {
        return self::$objekty[static::class][(int)$id] ?? null;
    }

    private static function doCache(self $object): void
    {
        self::$objekty[static::class][$object->id()] = $object;
    }

    /**
     * Načte a vrátí pole objektů s danými ID (může být prázdné)
     * @param array<int|string>|string $ids pole čísel nebo řetězec čísel oddělených čárkami
     * @return array<int, static>
     */
    public static function zIds(
        array | string $ids,
        bool           $zCache = false,
    ): array {
        if (is_string($ids)) {
            if ($ids === '') {
                return [];
            }
            assert(preg_match('~^([0-9]+,)*[0-9]+$~', $ids), 'Argument musí být čísla oddělená čárkami, pokud je to string');
            $ids = array_map('intval', explode(',', $ids));
        }
        if ($ids === []) {
            return [];
        }
        $cachedObjects = [];
        if ($zCache) {
            foreach ($ids as $index => $id) {
                $cachedObject = self::zCache($id);
                if ($cachedObject) {
                    $cachedObjects[] = $cachedObject;
                    unset($ids[$index]); // odstraní z pole, aby se nevolal dotaz na databázi
                }
            }
        }

        $freshObjects = $ids !== []
            ? self::zWhere((static::$aliasTabulky
                    ? (static::$aliasTabulky . '.')
                    : '') . static::$pk . ' IN (' . dbQa($ids) . ')')
            : [];
        if (!$zCache) {
            return $freshObjects;
        }
        foreach ($freshObjects as $freshObject) {
            self::doCache($freshObject);
        }

        return array_merge($cachedObjects, $freshObjects);
    }

    /** Načte a vrátí všechny objekty z databáze */
    public static function zVsech(bool $zCache = false): array
    {
        if ($zCache && (self::$objektyZVsech[static::class] ?? null) !== null) {
            return self::$objektyZVsech[static::class];
        }
        $objekty = self::zWhere('1');
        if ($zCache) {
            self::$objekty[static::class] ??= [];
            foreach ($objekty as $objekt) {
                self::$objektyZVsech[static::class][$objekt->id()] = $objekt;
            }
            self::$objekty[static::class] += (self::$objektyZVsech[static::class] ?? []);
        }

        return $objekty;
    }

    /** Načte a vrátí objekty pomocí dané where klauzule */
    protected static function zWhere(
        $where,
        $params = null,
        $extra = '',
    ): array {
        $o = dbQuery(static::dotaz("WHERE $where") . ' ' . $extra, $params); // static aby odděděná třída mohla přepsat dotaz na něco složitějšího
        $a = [];
        while ($r = mysqli_fetch_assoc($o)) {
            $a[] = new static($r); // static aby vznikaly objekty správné třídy
        }

        // TODO id jako klíč pole?
        return $a;
    }

    /**
     * Načte a vrátí objekt vyhovující where klauzuli nebo null
     * @throws RuntimeException pokud se načte více řádků
     */
    protected static function zWhereRadek(
        $where,
        $params = null,
    ) {
        $a = self::zWhere($where, $params);
        if (count($a) === 1) {
            return $a[0];
        }
        if (!$a) {
            return null;
        }
        throw new RuntimeException(sprintf("Více jak jeden řádek (%d) odpovídá where klauzuli '%s'", count($a), $where));
    }

    public function raw(): array
    {
        return $this->r;
    }
}
