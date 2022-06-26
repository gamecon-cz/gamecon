<?php

use Gamecon\Shop\Shop;
use Gamecon\Shop\TypPredmetu;

class ShopUbytovani
{

    /**
     * @param string[] $nazvyUbytovani
     * @param int $rok
     * @param bool $hodVyjimkuNeniLiPresne
     * @return int[]
     */
    public static function dejIdsPredmetuUbytovani(array $nazvyUbytovani, int $rok = ROK, bool $hodVyjimkuNeniLiPresne = true): array {
        $idsPredmetuUbytovani = array_map('intval', dbOneArray(<<<SQL
SELECT id_predmetu
FROM shop_predmety
WHERE TRIM(nazev) IN ($0 COLLATE utf8_czech_ci)
AND model_rok = $rok
SQL,
            [$nazvyUbytovani]
        ));
        if ($hodVyjimkuNeniLiPresne && count($nazvyUbytovani) !== count($idsPredmetuUbytovani)) {
            throw new Chyba(sprintf(
                "Nalezená IDs \"předmětů\" ubytování nesedí jedna ku jedné k hledaným názvům. %d názvů '%s', %d IDs %s",
                count($nazvyUbytovani),
                implode(',', $nazvyUbytovani),
                count($idsPredmetuUbytovani),
                implode(',', $idsPredmetuUbytovani),
            ));
        }
        return $idsPredmetuUbytovani;
    }

    public static function ulozPokojUzivatele(string $pokoj, ?int $prvniNoc, ?int $posledniNoc, Uzivatel $ucastnik): int {
        if ($pokoj === '' || ($prvniNoc === null && $posledniNoc === null)) {
            $mysqliResult = dbQuery(<<<SQL
DELETE FROM ubytovani
WHERE id_uzivatele = $0 AND rok = $1
SQL,
                [$ucastnik->id(), ROK]
            );
            return dbNumRows($mysqliResult);
        }

        if ($prvniNoc === null || $posledniNoc === null) {
            throw new Chyba("První a poslední noc musí být buďto obě prázdné, nebo obě zadané: první noc $prvniNoc, poslední noc $posledniNoc");
        }

        $zapsanoZmen = 0;
        $dny = range($prvniNoc, $posledniNoc);
        foreach ($dny as $den) {
            $mysqliResult = dbQuery(<<<SQL
INSERT INTO ubytovani(id_uzivatele, den, pokoj, rok)
    VALUES ($0, $1, $2, $3)
    ON DUPLICATE KEY UPDATE pokoj = $2
SQL,
                [$ucastnik->id(), $den, $pokoj, ROK]
            );
            $zapsanoZmen += dbNumRows($mysqliResult);
        }
        $mysqliResult = dbQuery(<<<SQL
DELETE FROM ubytovani
WHERE id_uzivatele = $0 AND den NOT IN ($1) AND rok = $2
SQL,
            [$ucastnik->id(), $dny, ROK]
        );
        $zapsanoZmen += dbNumRows($mysqliResult);

        return $zapsanoZmen;
    }

    public static function ulozSKymChceBytNaPokoji(string $ubytovanS, Uzivatel $ucastnik): int {
        $mysqliResult = dbQueryS('UPDATE uzivatele_hodnoty SET ubytovan_s=$0 WHERE id_uzivatele=' . $ucastnik->id(), [trim($ubytovanS)]);
        return dbNumRows($mysqliResult);
    }

    private static function smazLetosniNakupyUbytovaniUcastnika(Uzivatel $ucastnik, int $rok = ROK): int {
        $mysqliResult = dbQuery(<<<SQL
DELETE nakupy.*
FROM shop_nakupy AS nakupy
    JOIN shop_predmety AS predmety USING(id_predmetu)
WHERE nakupy.id_uzivatele=$0
  AND predmety.typ=$1
  AND nakupy.rok=$2
SQL,
            [$ucastnik->id(), TypPredmetu::UBYTOVANI, $rok]
        );
        return dbNumRows($mysqliResult);
    }

    /**
     * @param int $idPredmetu ID předmětu "ubytování v určitý den"
     * @param string[][][] $dny
     * @return bool jestli si uživatel objednává ubytování přes kapacitu
     */
    public static function ubytovaniPresKapacitu(int $idPredmetu, array $dny): bool {
        // načtení předmětu
        $predmet = null;
        foreach ($dny as $den) {
            foreach ($den as $moznyPredmet) {
                if ($moznyPredmet['id_predmetu'] == $idPredmetu) {
                    $predmet = $moznyPredmet;
                    break;
                }
            }
        }

        $nemelObjednanoDrive = (int)$predmet['kusu_uzivatele'] <= 0;
        $kapacitaVycerpana = $predmet['kusu_vyrobeno'] <= $predmet['kusu_prodano'];

        return $kapacitaVycerpana && $nemelObjednanoDrive;
    }

    /**
     * Kombinace účastník - pokoj - rok je tady považována za unikátní. Každý může mít jen jeden pokoj v jednom dni (např. "Trojlůžák čtvrtek") v jednom roce.
     * @param array|int[] $idPredmetuUbytovani
     * @return int
     * @throws Chyba
     */
    public static function ulozObjednaneUbytovaniUcastnika(
        array    $idsPredmetuUbytovani,
        Uzivatel $ucastnik,
        bool     $hlidatKapacituUbytovani = true,
        int      $rok = ROK
    ): int {
        // vložit jeho zaklikané věci - note: není zabezpečeno
        $sqlValuesArray = [];
        $idsPredmetuUbytovaniInt = [];
        foreach ($idsPredmetuUbytovani as $idPredmetuUbytovani) {
            if (!$idPredmetuUbytovani) {
                continue;
            }
            $idPredmetuUbytovani = (int)$idPredmetuUbytovani;
            $idsPredmetuUbytovaniInt[] = $idPredmetuUbytovani;
            if ($hlidatKapacituUbytovani && self::ubytovaniPresKapacitu($idPredmetuUbytovani, $ucastnik->dejShop()->ubytovani()->dny())) {
                throw new Chyba('Vybrané ubytování je už bohužel zabrané. Vyber si prosím jiné.');
            }
            $sqlValuesArray[] = <<<SQL
({$ucastnik->id()}, $idPredmetuUbytovani, $rok, (SELECT cena_aktualni FROM shop_predmety WHERE id_predmetu=$idPredmetuUbytovani), NOW())
SQL;
        }

        if (count($sqlValuesArray) === 0) {
            // nemáme co uložit, budeme pouze mazat
            return self::smazLetosniNakupyUbytovaniUcastnika($ucastnik);
        }

        $pocetZmen = 0;
        $sqlValues = implode(",\n", $sqlValuesArray);
        $tmpTable = uniqid('shop_nakupy_tmp', true);
        dbQuery(<<<SQL
CREATE TEMPORARY TABLE `$tmpTable`
(
    id_uzivatele INT NOT NULL,
    id_predmetu INT NOT NULL,
    rok SMALLINT NOT NULL,
    cena_nakupni DECIMAL(6, 2),
    datum DATETIME NOT NULL,
    PRIMARY KEY (id_uzivatele, id_predmetu, rok)
)
SQL
        );
        dbQuery(<<<SQL
INSERT IGNORE INTO `$tmpTable`(id_uzivatele,id_predmetu,rok,cena_nakupni,datum) VALUES $sqlValues
SQL
        );

        // smažeme nákupy ubytování, které nebudeme ukládat
        $mysqliResult = dbQuery(<<<SQL
DELETE shop_nakupy.*
FROM shop_nakupy
JOIN shop_predmety on shop_predmety.id_predmetu = shop_nakupy.id_predmetu
WHERE shop_nakupy.id_uzivatele = {$ucastnik->id()}
    AND shop_nakupy.rok = $rok
    AND shop_nakupy.id_predmetu NOT IN ($0) -- není to hodnota kterou chceme mít uloženu
    AND shop_predmety.typ = $1
SQL,
            [$idsPredmetuUbytovaniInt, TypPredmetu::UBYTOVANI]
        );
        $pocetZmen += dbNumRows($mysqliResult);

        // smažeme připravené hodnoty, které už máme
        dbQuery(<<<SQL
DELETE `$tmpTable`.*
FROM `$tmpTable`
LEFT JOIN shop_nakupy USING(id_uzivatele,id_predmetu,rok)
WHERE shop_nakupy.id_uzivatele IS NOT NULL -- tuhle kombinaci "typ ubytování, uživatel a rok" už máme (kombinace LEFT JOIN a IS NOT NULL)
    AND shop_nakupy.id_uzivatele = {$ucastnik->id()}
    AND shop_nakupy.rok = $rok
    AND shop_nakupy.id_predmetu IN ($0)
SQL,
            [$idsPredmetuUbytovaniInt]
        );

        // konečně vložíme pouze nové nebo změněné ubytování
        $mysqliResult = dbQuery(<<<SQL
INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni, datum)
SELECT tmp.id_uzivatele, tmp.id_predmetu, tmp.rok, tmp.cena_nakupni, tmp.datum
FROM `$tmpTable` AS tmp
SQL,
        );
        $pocetZmen += dbNumRows($mysqliResult);
        dbQuery(<<<SQL
DROP TEMPORARY TABLE IF EXISTS `$tmpTable`
SQL
        );
        return $pocetZmen;
    }

    private $dny;     // asoc. 2D pole [den][typ] => předmět
    private $typy;    // asoc. pole [typ] => předmět sloužící jako vzor daného typu
    private $pnDny = 'shopUbytovaniDny';
    private $pnPokoj = 'shopUbytovaniPokoj';
    private $pnCovidFreePotvrzeni = 'shopCovidFreePotvrzeni';
    private $u;

    public function __construct(array $predmety, Uzivatel $uzivatel) {
        $this->u = $uzivatel;
        foreach ($predmety as $p) {
            $nazev = Shop::bezDne($p['nazev']);
            if (!isset($this->typy[$nazev])) {
                $this->typy[$nazev] = $p;
            }
            $this->dny[$p['ubytovani_den']][$nazev] = $p;
        }
    }

    /**
     * @return string[][][]
     */
    public function dny(): array {
        return $this->dny;
    }

    public function html() {
        $t = new XTemplate(__DIR__ . '/shop-ubytovani.xtpl');
        $t->assign([
            'spolubydlici' => dbOneCol('SELECT ubytovan_s FROM uzivatele_hodnoty WHERE id_uzivatele=' . $this->u->id()),
            'postnameSpolubydlici' => $this->pnPokoj,
            'uzivatele' => $this->mozniUzivatele(),
        ]);
        $this->htmlDny($t);
        // sloupce popisků
        foreach ($this->typy as $typ => $predmet) {
            $t->assign([
                'typ' => $typ,
                'hint' => $predmet['popis'],
                'cena' => round($predmet['cena_aktualni']),
            ]);
            $t->parse($predmet['popis'] ? 'ubytovani.typ.hinted' : 'ubytovani.typ.normal');
            $t->parse('ubytovani.typ');
        }

        // specifická info podle uživatele a stavu nabídky
        if (reset($this->typy)['stav'] == 3) {
            $t->parse('ubytovani.konec');
        }

        $t->parse('ubytovani');
        return $t->text('ubytovani');
    }

    /** Zparsuje šablonu s ubytováním po dnech */
    private function htmlDny(XTemplate $t) {
        foreach ($this->dny as $den => $typy) { // typy _v daný den_
            $ubytovan = false;
            $typVzor = reset($typy);
            $t->assign('postnameDen', $this->pnDny . '[' . $den . ']');
            foreach ($this->typy as $typ => $rozsah) {
                $checked = '';
                if ($this->ubytovan($den, $typ)) {
                    $ubytovan = true;
                    $checked = 'checked';
                }
                $t->assign([
                    'idPredmetu' => isset($this->dny[$den][$typ]) ? $this->dny[$den][$typ]['id_predmetu'] : null,
                    'checked' => $checked,
                    'disabled' => !$ubytovan && (!$this->existujeUbytovani($den, $typ) || $this->plno($den, $typ)) ? 'disabled' : '',
                    'obsazeno' => $this->obsazenoMist($den, $typ),
                    'kapacita' => $this->kapacita($den, $typ),
                ])->parse('ubytovani.den.typ');
            }
            $t->assign([
                'den' => mb_ucfirst(substr($typVzor['nazev'], strrpos($typVzor['nazev'], ' ') + 1)),
                'checked' => $ubytovan ? '' : 'checked',
                'disabled' => $ubytovan && $typVzor['stav'] == 3 && !$typVzor['nabizet'] ? 'disabled' : '',
            ])->parse('ubytovani.den');
        }
    }

    /**
     * @return bool
     * @throws Chyba
     */
    public function zpracuj(): bool {
        if (!isset($_POST[$this->pnDny])) {
            return false;
        }

        // vložit jeho zaklikané věci - note: není zabezpečeno
        self::ulozObjednaneUbytovaniUcastnika($_POST[$this->pnDny], $this->u);

        // uložit s kým chce být na pokoji
        self::ulozSKymChceBytNaPokoji($_POST[$this->pnPokoj] ?? '', $this->u);

        return true;
    }

    /////////////
    // private //
    /////////////

    /** Vrátí, jestli daná kombinace den a typ je validní. */
    private function existujeUbytovani($den, $typ) {
        return isset($this->dny[$den][$typ])
            && $this->dny[$den][$typ]['nabizet'] == true;
    }

    /** Vrátí kapacitu */
    private function kapacita($den, $typ) {
        if (!isset($this->dny[$den][$typ])) return 0;
        $ub = $this->dny[$den][$typ];
        return max(0, $ub['kusu_vyrobeno']);
    }

    /** Vrátí počet obsazených míst pro daný den a typu ubytování */
    private function obsazenoMist($den, $typ) {
        return $this->kapacita($den, $typ) - $this->zbyvaMist($den, $typ);
    }

    /** Vrátí, jestli je v daný den a typ ubytování plno */
    private function plno($den, $typ) {
        return $this->zbyvaMist($den, $typ) <= 0;
    }

    /**
     * @param int $idPredmetu ID předmětu "ubytování v určitý den"
     * @return bool jestli si uživatel objednává ubytování přes kapacitu
     */
    private function presKapacitu($idPredmetu) {
        // načtení předmětu
        $predmet = null;
        foreach ($this->dny as $den) {
            foreach ($den as $moznyPredmet) {
                if ($moznyPredmet['id_predmetu'] == $idPredmetu) {
                    $predmet = $moznyPredmet;
                    break;
                }
            }
        }

        $melObjednanoDrive = $predmet['kusu_uzivatele'] > 0;
        $kapacitaVycerpana = $predmet['kusu_vyrobeno'] <= $predmet['kusu_prodano'];

        return $kapacitaVycerpana && !$melObjednanoDrive;
    }

    /**
     * Vrátí, jestli uživatel pro tento shop má ubytování v kombinaci den, typ
     * @param int $den číslo dne jak je v databázi
     * @param string $typ typ ubytování ve smyslu názvu z DB bez posledního slova
     * @return bool je ubytován?
     */
    private function ubytovan($den, $typ) {
        return isset($this->dny[$den][$typ])
            && $this->dny[$den][$typ]['kusu_uzivatele'] > 0;
    }

    /** Vrátí počet volných míst */
    private function zbyvaMist($den, $typ) {
        if (!isset($this->dny[$den][$typ])) {
            return 0;
        }
        $ub = $this->dny[$den][$typ];
        return max(0, $ub['kusu_vyrobeno'] - $ub['kusu_prodano']);
    }

    /**
     * Vrátí seznam uživatelů ve formátu Jméno Příjmení (Login) tak aby byl zpra-
     * covatelný neajaxovým našeptávátkem (čili ["položka","položka",...])
     */
    protected function mozniUzivatele() {
        $a = [];
        $o = dbQuery("
      SELECT CONCAT(jmeno_uzivatele,' ',prijmeni_uzivatele,' (',login_uzivatele,')')
      FROM uzivatele_hodnoty
      WHERE jmeno_uzivatele != '' AND prijmeni_uzivatele != '' AND id_uzivatele != $1
    ", [$this->u->id()]);
        while ($u = mysqli_fetch_row($o)) {
            $a[] = $u[0];
        }
        return json_encode($a);
    }

    public function kratkyPopis(string $oddelovacDalsihoRadku = '<br>'): string {
        $dnyPoTypech = [];
        foreach ($this->dny as $cisloDne => $typy) { // typy _v daný den_
            $typVzor = reset($typy);
            foreach ($this->typy as $typ => $rozsah) {
                if ($this->ubytovan($cisloDne, $typ)) {
                    $poziceZaPosledniMezerou = strrpos($typVzor['nazev'], ' ') + 1;
                    $nazevDne = mb_strtolower(substr($typVzor['nazev'], $poziceZaPosledniMezerou));
                    $zkratkaDne = mb_substr($nazevDne, 0, 2);
                    $dnyPoTypech[$typ][] = $zkratkaDne;
                }
            }
        }
        $typySeDny = [];
        foreach ($dnyPoTypech as $typ => $dny) {
            $typySeDny[] = "$typ: " . implode(',', $dny);
        }
        return implode($oddelovacDalsihoRadku, $typySeDny);
    }
}
