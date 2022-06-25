<?php

use Gamecon\Shop\Shop;
use Gamecon\Shop\TypPredmetu;

class ShopUbytovani
{
    public static function ulozUbytovaniUzivatele(string $pokoj, int $prvniNoc, int $posledniNoc, Uzivatel $ucastnik): int {
        $zapsanoZmen = 0;
        if ($pokoj === '') {
            $mysqliResult = dbQuery(<<<SQL
DELETE FROM ubytovani
WHERE id_uzivatele = $0 AND rok = $1
SQL,
                [$ucastnik->id(), ROK]
            );
            return dbNumRows($mysqliResult);
        }

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

    public static function smazLetosniNakupyUbytovaniUcastnika(Uzivatel $ucastnik): int {
        $mysqliResult = dbQuery(<<<SQL
DELETE nakupy.*
FROM shop_nakupy AS nakupy
    JOIN shop_predmety AS predmety USING(id_predmetu)
WHERE nakupy.id_uzivatele=$0
  AND predmety.typ=$1
  AND nakupy.rok=$2
SQL,
            [$ucastnik->id(), TypPredmetu::UBYTOVANI, ROK]
        );
        return dbNumRows($mysqliResult);
    }

    /**
     * @param int $idPredmetu ID předmětu "ubytování v určitý den"
     * @param string[][][] $dny
     * @param Uzivatel $ucastnik
     * @return bool jestli si uživatel objednává ubytování přes kapacitu
     */
    public static function ubytovaniPresKapacitu(int $idPredmetu, array $dny, Uzivatel $ucastnik): bool {
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
     * @param array|int[] $idPredmetuUbytovani
     * @return int
     * @throws Chyba
     */
    public static function ulozUbytovaniUcastnika(array $idsPredmetuUbytovani, Uzivatel $ucastnik): int {
        // vložit jeho zaklikané věci - note: není zabezpečeno
        $sqlValuesArray = [];
        $rok = ROK;
        $pocetZmen = 0;
        foreach ($idsPredmetuUbytovani as $predmet) {
            if (!$predmet) {
                continue;
            }
            $predmet = (int)$predmet;
            $sqlValuesArray[] = "({$ucastnik->id()}, $predmet, $rok, (SELECT cena_aktualni FROM shop_predmety WHERE id_predmetu=$predmet) ,NOW())";
            if (self::ubytovaniPresKapacitu($predmet, $ucastnik->dejShop()->ubytovani()->dny(), $ucastnik)) {
                throw new Chyba('Vybrané ubytování je už bohužel zabrané. Vyber si prosím jiné.');
            }
        }
        if (count($sqlValuesArray) > 0) {
            $sqlValues = implode("\n", $sqlValuesArray);
            $mysqliResult = dbQuery(<<<SQL
INSERT INTO shop_nakupy(id_uzivatele,id_predmetu,rok,cena_nakupni,datum) VALUES $sqlValues
SQL
            );
            $pocetZmen += dbNumRows($mysqliResult);
        }
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

        // smazat veškeré stávající ubytování uživatele
        self::smazLetosniNakupyUbytovaniUcastnika($this->u);

        // vložit jeho zaklikané věci - note: není zabezpečeno
        self::ulozUbytovaniUcastnika($_POST[$this->pnDny], $this->u);

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
