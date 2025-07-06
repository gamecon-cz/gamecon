<?php

namespace Gamecon\Shop;

use Gamecon\Cas\DateTimeCz;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Chyba;
use Gamecon\Pravo;
use Gamecon\Uzivatel\Registrace;
use Uzivatel;
use Gamecon\XTemplate\XTemplate;
use Gamecon\Shop\SqlStruktura\PredmetSqlStruktura as Sql;

class ShopUbytovani
{
    /**
     * @param string[] $nazvyUbytovani
     * @param int $rok
     * @param bool $hodVyjimkuNeniLiPresne
     * @return int[]
     */
    public static function dejIdsPredmetuUbytovani(
        array $nazvyUbytovani,
        int   $rok = ROCNIK,
        bool  $hodVyjimkuNeniLiPresne = true,
    ): array {
        $idsPredmetuUbytovani = array_map('intval', dbOneArray(<<<SQL
SELECT id_predmetu
FROM shop_predmety
WHERE TRIM(nazev) IN ($0 COLLATE utf8_czech_ci)
AND model_rok = $rok
SQL,
            [$nazvyUbytovani],
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

    public static function ulozPokojUzivatele(
        string   $pokoj,
        ?int     $prvniNoc,
        ?int     $posledniNoc,
        Uzivatel $ucastnik,
    ): int {
        if ($pokoj === '' || ($prvniNoc === null && $posledniNoc === null)) {
            $mysqliResult = dbQuery(<<<SQL
DELETE FROM ubytovani
WHERE id_uzivatele = $0 AND rok = $1
SQL,
                [$ucastnik->id(), ROCNIK],
            );

            return dbAffectedOrNumRows($mysqliResult);
        }

        if ($prvniNoc === null || $posledniNoc === null) {
            throw new Chyba("První a poslední noc musí být buďto obě prázdné, nebo obě zadané: první noc $prvniNoc, poslední noc $posledniNoc");
        }

        $zapsanoZmen = 0;
        $dny         = range($prvniNoc, $posledniNoc);
        foreach ($dny as $den) {
            $mysqliResult = dbQuery(<<<SQL
INSERT INTO ubytovani(id_uzivatele, den, pokoj, rok)
    VALUES ($0, $1, $2, $3)
    ON DUPLICATE KEY UPDATE pokoj = $2
SQL,
                [$ucastnik->id(), $den, $pokoj, ROCNIK],
            );
            $zapsanoZmen  += dbAffectedOrNumRows($mysqliResult);
        }
        $mysqliResult = dbQuery(<<<SQL
DELETE FROM ubytovani
WHERE id_uzivatele = $0 AND den NOT IN ($1) AND rok = $2
SQL,
            [$ucastnik->id(), $dny, ROCNIK],
        );
        $zapsanoZmen  += dbAffectedOrNumRows($mysqliResult);

        return $zapsanoZmen;
    }

    public static function ulozSKymChceBytNaPokoji(
        string   $ubytovanS,
        Uzivatel $ucastnik,
    ): int {
        if ($ucastnik->ubytovanS() === $ubytovanS) {
            return 0;
        }
        $ucastnik->ubytovanS($ubytovanS);
        $mysqliResult = dbQueryS('UPDATE uzivatele_hodnoty SET ubytovan_s=$0 WHERE id_uzivatele=' . $ucastnik->id(), [trim($ubytovanS)]);

        return dbAffectedOrNumRows($mysqliResult);
    }

    private static function smazLetosniNakupyUbytovaniUcastnika(
        Uzivatel $ucastnik,
        int      $rok = ROCNIK,
    ): int {
        $mysqliResult = dbQuery(<<<SQL
DELETE nakupy.*
FROM shop_nakupy AS nakupy
    JOIN shop_predmety AS predmety USING(id_predmetu)
WHERE nakupy.id_uzivatele=$0
  AND predmety.typ=$1
  AND nakupy.rok=$2
SQL,
            [$ucastnik->id(), TypPredmetu::UBYTOVANI, $rok],
        );

        return dbAffectedOrNumRows($mysqliResult);
    }

    /**
     * @param int $idPredmetu ID předmětu "ubytování v určitý den"
     * @param string[][][] $dny
     * @return bool jestli si uživatel objednává ubytování přes kapacitu
     */
    public static function ubytovaniPresKapacitu(
        int   $idPredmetu,
        array $dny,
    ): bool {
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
        $kapacitaVycerpana   = $predmet['kusu_vyrobeno'] <= $predmet['kusu_prodano'];

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
        int      $rok = ROCNIK,
    ): int {
        // vložit jeho zaklikané věci - note: není zabezpečeno
        $sqlValuesArray          = [];
        $idsPredmetuUbytovaniInt = [];
        foreach ($idsPredmetuUbytovani as $idPredmetuUbytovani) {
            if (!$idPredmetuUbytovani) {
                continue;
            }
            $idPredmetuUbytovani       = (int)$idPredmetuUbytovani;
            $idsPredmetuUbytovaniInt[] = $idPredmetuUbytovani;
            if ($hlidatKapacituUbytovani && self::ubytovaniPresKapacitu($idPredmetuUbytovani, $ucastnik->shop()->ubytovani()->mozneDny())) {
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
        $tmpTable  = uniqid('shop_nakupy_tmp', true);
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
SQL,
        );
        dbQuery(<<<SQL
INSERT IGNORE INTO `$tmpTable`(id_uzivatele,id_predmetu,rok,cena_nakupni,datum) VALUES $sqlValues
SQL,
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
            [$idsPredmetuUbytovaniInt, TypPredmetu::UBYTOVANI],
        );
        $pocetZmen    += dbAffectedOrNumRows($mysqliResult);

        // smažeme připravené hodnoty, které už máme
        dbQuery(<<<SQL
DELETE `$tmpTable`.*
FROM `$tmpTable`
LEFT JOIN shop_nakupy
    ON `$tmpTable`.id_uzivatele = shop_nakupy.id_uzivatele
    AND `$tmpTable`.id_predmetu = shop_nakupy.id_predmetu
    AND `$tmpTable`.rok = shop_nakupy.rok
WHERE shop_nakupy.id_uzivatele IS NOT NULL -- tuhle kombinaci "typ ubytování, uživatel a rok" už máme (kombinace LEFT JOIN a IS NOT NULL)
    AND shop_nakupy.id_uzivatele = {$ucastnik->id()}
    AND shop_nakupy.rok = $rok
    AND shop_nakupy.id_predmetu IN ($0)
SQL,
            [$idsPredmetuUbytovaniInt],
        );

        // konečně vložíme pouze nové nebo změněné ubytování
        $mysqliResult = dbQuery(<<<SQL
INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni, datum)
SELECT tmp.id_uzivatele, tmp.id_predmetu, tmp.rok, tmp.cena_nakupni, tmp.datum
FROM `$tmpTable` AS tmp
SQL,
        );
        $pocetZmen    += dbAffectedOrNumRows($mysqliResult);
        dbQuery(<<<SQL
DROP TEMPORARY TABLE IF EXISTS `$tmpTable`
SQL,
        );

        return $pocetZmen;
    }

    private Registrace $registrace;
    private            $mozneDny        = []; // pouze ubytování, které si může uživatel koupit
    private            $mozneTypy       = []; // asoc. pole [typ] => předmět sloužící jako vzor daného typu
    private            $ubytovanPoDnech = []; // všechna ubytování
    private            $pnDny           = 'shopUbytovaniDny';
    private            $pnPokoj         = 'shopUbytovaniPokoj';

    public function __construct(
        array                               $predmety,
        private readonly Uzivatel           $ubytovany,
        private readonly Uzivatel           $objednatel,
        private readonly KontextZobrazeni   $kontextZobrazeni,
        private readonly SystemoveNastaveni $systemoveNastaveni,
    ) {
        foreach ($predmety as $predmet) {
            $nazev = Shop::bezDne($predmet[Sql::NAZEV]);
            if ($this->maPravoZobrazitUbytovani((int)$predmet[Sql::UBYTOVANI_DEN])) {
                if (!isset($this->mozneTypy[$nazev])) {
                    $this->mozneTypy[$nazev] = $predmet;
                }
                $predmet['nabizet'] = $predmet['nabizet'] && $this->maPravoObjednatUbytovani((int)$predmet[Sql::UBYTOVANI_DEN]);

                $this->mozneDny[$predmet[Sql::UBYTOVANI_DEN]][$nazev] = $predmet;
            }
            $this->ubytovanPoDnech[$predmet[Sql::UBYTOVANI_DEN]][$nazev] = $predmet;
            // else z neděle na pondělí už není veřejně nabízené ubytování https://trello.com/c/rP47BsUD/940-%C3%BApravy-p%C5%99ihl%C3%A1%C5%A1ky-mastercard-2023
        }
        $this->registrace = new Registrace($this->systemoveNastaveni, $ubytovany);
    }

    /**
     * @return string[][][]
     */
    public function mozneDny(): array
    {
        return $this->mozneDny;
    }

    public function mozneTypy(): array
    {
        return $this->mozneTypy;
    }

    public function ubytovanPoDnech(): array
    {
        return $this->ubytovanPoDnech;
    }

    public function postnameDen(): string
    {
        return $this->pnDny;
    }

    public function uzivatel(): Uzivatel
    {
        return $this->ubytovany;
    }

    private function maPravoNaPostel(): bool
    {
        /*
         * funkce pro kontrolu jestli uživatel má právo na jiné ubytování než spacák
         * důvod výpadek budovy C v roce 2024
         *
         * použití ve spolupráci s proměnno $omezitNaSpacáky
         * */
        return $this->uzivatel()->jeVypravec() || $this->uzivatel()->jeOrganizator() ||
               $this->uzivatel()->jeHerman() || $this->uzivatel()->jePartner() ||
               $this->uzivatel()->jeInfopultak() ||
               $this->uzivatel()->jeZazemi();
    }

    private function omluvaZaNedostupneUbytovani(): string
    {
        /*
         *
         * Omluvný text pro běžné účastníky kváli omezení na čistě spacýky
         * řízená proměnnou $omezitNaSpacáky
         *
         * výpadek budovy C v roce 2024
         * */

        return $this->systemoveNastaveni->jeOmezeniUbytovaniPouzeNaSpacaky() && !$this->maPravoNaPostel()
            ? self::omluvaZaNedostupneUbytovaniText()
            : '';
    }

    private static function omluvaZaNedostupneUbytovaniText(): string
    {
        return 'Vzhledem k rekonstrukci budovy C jsme museli letos zrušit možnost ubytování na postelích. Jako částečnou kompenzaci nabízíme větší počet míst pro spaní ve spacácích v tělocvičnách.
Situace nás mrzí, přesto věříme, že tě od účasti na letošním GC neodradí a v létě se spolu uvidíme. Děkujeme za tvou podporu.
Více informací najdeš <a href="https://gamecon.cz/blog/ubytovani-2024">zde</a>.';
    }

    public function ubytovaniHtml(
        bool $muzeEditovatUkoncenyProdej = false,
        bool $muzeUbytovatPresKapacitu = false,
    ) {
        $t                           = new XTemplate(__DIR__ . '/templates/shop-ubytovani.xtpl');
        $omluvaZaNedostupneUbytovani = $this->omluvaZaNedostupneUbytovani();
        if ($omluvaZaNedostupneUbytovani !== '') {
            $t->assign([
                'omluvaZaNedostupneUbytovani' => $omluvaZaNedostupneUbytovani,
            ]);
            $t->parse('ubytovani.omluvaZaNedostupneUbytovani');
        }
        $t->assign([
            'shopUbytovaniJs'      => URL_WEBU . '/soubory/blackarrow/shop/shop-ubytovani.js?version='
                                      . md5_file(WWW . '/soubory/blackarrow/shop/shop-ubytovani.js'),
            'spolubydlici'         => htmlspecialchars(dbOneCol('SELECT ubytovan_s FROM uzivatele_hodnoty WHERE id_uzivatele=' . $this->ubytovany->id()) ?? ''),
            'postnameSpolubydlici' => $this->pnPokoj,
            'uzivatele'            => $this->mozniUzivatele(),
            'povinneUdaje'         => $this->registrace->povinneUdajeProUbytovaniHtml(
                'Povinné údaje pro ubytování',
                'Vzhledem k zákonným povinnostem bohužel musíme odevzdávat seznam ubytovaných s následujícími osobními údaji. Chybné vyplnění následujících polí může u infopultu vést k vykázání na konec fronty, aby náprava nezdržovala odbavení ostatních! (Případné stížnosti prosíme rovnou vašim politickým zástupcům.)',
            ),
        ]);
        $this->htmlDny($t, $muzeEditovatUkoncenyProdej);
        $nemuzeSiObjednatPokoj = $this->systemoveNastaveni->jeOmezeniUbytovaniPouzeNaSpacaky()
                                 && !$this->maPravoNaPostel();
        // sloupce popisků
        foreach ($this->mozneTypy as $typ => $predmet) {
            if ($nemuzeSiObjednatPokoj && $typ != 'Spacák') {
                continue;
            }

            $t->assign([
                'typ'  => $typ,
                'hint' => $predmet[Sql::POPIS],
                'cena' => round($predmet[Sql::CENA_AKTUALNI]),
            ]);
            $t->parse($predmet['popis']
                ? 'ubytovani.typ.hinted'
                : 'ubytovani.typ.normal');
            $t->parse('ubytovani.typ');
        }

        // specifická info podle uživatele a stavu nabídky
        if ((!$muzeEditovatUkoncenyProdej && $this->systemoveNastaveni->prodejUbytovaniUkoncen())
            || !$this->mozneTypy
            || $this->vsechnPozastaveno()
        ) {
            $t->parse('ubytovani.konec');
        }

        if ($muzeUbytovatPresKapacitu) {
            $presKapacituBtn = !empty($_SESSION['presKapacituBtn']);

            $t->assign(
                'presKapacituText',
                $presKapacituBtn
                ? 'zrušit přes kapacitu'
                : 'přes kapacitu'
            );

            $t->assign(
                'presKapacituFkce',
                $presKapacituBtn
                ? 'presKapacitu()'
                : 'zrusPresKapacitu()'
            );

            $t->parse('ubytovani.ubytovaniPresKapacitu');
        }

        $t->parse('ubytovani');

        return $t->text('ubytovani');
    }

    private function vsechnPozastaveno(): bool
    {
        foreach ($this->mozneTypy as $moznyTyp) {
            if ($moznyTyp[Sql::STAV] != StavPredmetu::POZASTAVENY) {
                return false;
            }
        }

        return true;
    }

    /** Zparsuje šablonu s ubytováním po dnech */
    private function htmlDny(
        XTemplate $t,
        bool      $muzeEditovatUkoncenyProdej,
    ) {
        $muzeObjednatJednuNoc   = $this->muzeObjednatJednuNoc();
        $prodejUbytovaniUkoncen = !$muzeEditovatUkoncenyProdej && $this->systemoveNastaveni->prodejUbytovaniUkoncen();
        $nemuzeSiObjednatPokoj  = $this->systemoveNastaveni->jeOmezeniUbytovaniPouzeNaSpacaky()
                                  && !$this->maPravoNaPostel();
        foreach ($this->mozneDny as $den => $typy) { // typy _v daný den_
            if (!$muzeObjednatJednuNoc && $den > 1) {
                // uživatel nemá právo na objednání jedné noci, tak se mu to zabalilo do jednoho checkboxu
                break;
            }
            $typVzor = reset($typy);
            $t->assign('postnameDen', $this->pnDny . '[' . $den . ']');
            $ubytovanVeDni = false;
            foreach ($this->mozneTypy as $typ => $rozsah) {
                if ($nemuzeSiObjednatPokoj && $typ != 'Spacák') {
                    continue;
                }

                $ubytovanVeDniATypu = false;
                $checked            = '';
                if ($this->ubytovan($den, $typ)) {
                    $ubytovanVeDniATypu = true;
                    $checked            = 'checked';
                }
                $ubytovanVeDni = $ubytovanVeDni || $ubytovanVeDniATypu;
                $t->assign([
                    'idPredmetu' => isset($this->mozneDny[$den][$typ])
                        ? $this->mozneDny[$den][$typ]['id_predmetu']
                        : null,
                    'checked'    => $checked,
                    'disabled'   => $this->totoUbytovaniVyrazeno(
                        $checked,
                        $prodejUbytovaniUkoncen,
                        $ubytovanVeDniATypu,
                        $den,
                        $typ,
                    )
                        ? 'disabled'
                        : '',
                    'obsazeno'   => $this->obsazenoMist($den, $typ),
                    'kapacita'   => $this->kapacita($den, $typ),
                    'typ'        => $typ,
                ])->parse('ubytovani.den.typ');
            }

            $denText = !$muzeObjednatJednuNoc && $den == 1
                ? "Čt–Ne (3 noci)"
                : $this->dejNazevJakoRozsahDnu((int)$typVzor[Sql::UBYTOVANI_DEN]);

            // data pro názvy dnů a pro "Žádné" ubytování
            $t->assign([
                'den'      => $denText,
                'checked'  => $ubytovanVeDni
                    ? ''
                    : 'checked', // checked = "Žádné" ubytování
                'disabled' => $prodejUbytovaniUkoncen || ($ubytovanVeDni && $typVzor['stav'] == Shop::STAV_POZASTAVENY && !$typVzor['nabizet'])
                    ? 'disabled'
                    : '',
            ]);
            $t->parse('ubytovani.den');
        }
    }

    private function totoUbytovaniVyrazeno(
        bool         $checked,
        bool         $prodejUbytovaniUkoncen,
        bool         $ubytovanVeDniATypu,
        int | string $den,
        int | string $typ,
    ): bool {
        return (!$checked // GUI neumí checked disabled, tak nesmíme dát disabled, když je chcecked
                && ($prodejUbytovaniUkoncen
                    || (!$ubytovanVeDniATypu
                        && (!$this->existujeUbytovani($den, $typ) || $this->plno($den, $typ) || $this->neprodejne($den, $typ))
                    )
                    || !$this->maPravoObjednatUbytovani($den)
                )
        );
    }

    private function dejNazevJakoRozsahDnu(int $indexDneKZacatkuGc): string
    {
        $poradiDneVTydnu = DateTimeGamecon::poradiDneVTydnuPodleIndexuOdZacatkuGameconu($indexDneKZacatkuGc);

        return DateTimeCz::poradiDneVTydnuNaPrelomDnuVeZkratkach($poradiDneVTydnu, true);
    }

    public function zpracuj(
        bool $vcetneSpolubydliciho = true,
        bool $hlidatKapacituUbytovani = true,
    ): bool {
        if (!isset($_POST[$this->pnDny])) {
            return false;
        }

        $dny = [];

        if (!$this->muzeObjednatJednuNoc()) {
            $postDny = $_POST[$this->pnDny];

            if (isset($postDny[0])) {
                $dny[0] = $postDny[0];
            }

            if (!empty($postDny[1])) {
                $id         = $postDny[1];
                $jedenZeDnu = Predmet::zId($id);
                $druhUbytka = preg_split('~\s+~', $jedenZeDnu->nazev())[0];
                $vsechnyDny = dbOneArray(<<<SQL
                    SELECT id_predmetu FROM shop_predmety WHERE model_rok = $0 AND typ = $1 AND nazev LIKE $2
                    AND ubytovani_den IN (1, 2, 3)
                SQL,
                    [$jedenZeDnu->modelRok(), $jedenZeDnu->typ(), $druhUbytka . '%'],
                );

                $dny[1] = (string)$vsechnyDny[0];
                $dny[2] = (string)$vsechnyDny[1];
                $dny[3] = (string)$vsechnyDny[2];
            }
        } else {
            $dny = $_POST[$this->pnDny];
        }

        self::ulozObjednaneUbytovaniUcastnika($dny, $this->ubytovany, $hlidatKapacituUbytovani);

        if ($vcetneSpolubydliciho) {
            // uložit s kým chce být na pokoji
            self::ulozSKymChceBytNaPokoji($_POST[$this->pnPokoj] ?? '', $this->ubytovany);
        }

        $this->registrace->ulozZmeny(); // povinné údaje pro ubytování

        return true;
    }

    private function muzeObjednatJednuNoc(): bool
    {
        return $this->objednatel->maPravo(Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC);
    }

    private function maPravoZobrazitUbytovani(int $poradiHernihoDne): bool
    {
        return $poradiHernihoDne !== DateTimeGamecon::PORADI_HERNIHO_DNE_NEDELE
               || $this->ubytovany->maPravo(Pravo::UBYTOVANI_NEDELNI_NOC_NABIZET)
               || $this->ubytovany->maPravo(Pravo::UBYTOVANI_NEDELNI_NOC_ZDARMA)
               || $this->objednatel->jeOrganizator()
               || ($this->objednatel->jeInfopultak() && $this->kontextZobrazeni === KontextZobrazeni::ADMIN);
    }

    private function maPravoObjednatUbytovani(int $poradiHernihoDne): bool
    {
        return $this->maPravoZobrazitUbytovani($poradiHernihoDne)
               && ($poradiHernihoDne !== DateTimeGamecon::PORADI_HERNIHO_DNE_NEDELE
                   || $this->ubytovany->maPravo(Pravo::UBYTOVANI_NEDELNI_NOC_NABIZET)
                   || $this->ubytovany->maPravo(Pravo::UBYTOVANI_NEDELNI_NOC_ZDARMA)
                   || $this->objednatel->jeOrganizator()
               );
    }

    /** Vrátí, jestli daná kombinace den a typ je validní. */
    public function existujeUbytovani(
        $den,
        $typ,
    ) {
        return isset($this->mozneDny[$den][$typ])
               && $this->mozneDny[$den][$typ]['nabizet'] == true;
    }

    /** Vrátí kapacitu */
    public function kapacita(
        $den,
        $typ,
    ) {
        if (!isset($this->mozneDny[$den][$typ])) return 0;
        $ub = $this->mozneDny[$den][$typ];

        return max(0, $ub['kusu_vyrobeno']);
    }

    /** Vrátí počet obsazených míst pro daný den a typu ubytování */
    public function obsazenoMist(
        $den,
        $typ,
    ) {
        return $this->kapacita($den, $typ) - $this->zbyvaMist($den, $typ);
    }

    /** Vrátí, jestli je v daný den a typ ubytování plno */
    public function plno(
        $den,
        $typ,
    ): bool {
        return $this->zbyvaMist($den, $typ) <= 0;
    }

    private function neprodejne(
        int | string $den,
        int | string $typ,
    ): bool {
        return (int)$this->mozneDny[$den][$typ]['stav'] === StavPredmetu::POZASTAVENY;
    }

    /**
     * Vrátí, jestli uživatel pro tento shop má ubytování v kombinaci den, typ
     * @param int $den číslo dne jak je v databázi
     * @param string $typ typ ubytování ve smyslu názvu z DB bez posledního slova
     * @return bool je ubytován?
     */
    public function ubytovan(
        $den,
        $typ,
    ): bool {
        return isset($this->ubytovanPoDnech[$den][$typ])
               && $this->ubytovanPoDnech[$den][$typ]['kusu_uzivatele'] > 0;
    }

    public function veKterychDnechJeUbytovan(): array
    {
        $dnyUbytovani = [];
        foreach ($this->ubytovanPoDnech() as $den => $typyADetaily) {
            foreach ($typyADetaily as /* $typUbytovani => */ $detail) {
                if ($detail['kusu_uzivatele'] > 0) {
                    $dnyUbytovani[] = $den;
                }
            }
        }

        return $dnyUbytovani;
    }

    public function maObjednaneUbytovani(): bool
    {
        return count($this->veKterychDnechJeUbytovan()) > 0;
    }

    /** Vrátí počet volných míst */
    public function zbyvaMist(
        $den,
        $typ,
    ): int {
        if (!isset($this->mozneDny[$den][$typ])) {
            return 0;
        }
        $ub = $this->mozneDny[$den][$typ];

        return (int)max(0, $ub['kusu_vyrobeno'] - $ub['kusu_prodano']);
    }

    /**
     * TODO: dotahuje pouze data z tabulky uživatele, nemá v téhle třídě co dělat, má být součástí uživatele
     * Vrátí seznam uživatelů ve formátu Jméno Příjmení (Login) tak aby byl zpra-
     * covatelný neajaxovým našeptávátkem (čili ["položka","položka",...])
     */
    public function mozniUzivatele()
    {
        $a = [];
        $o = dbQuery("
      SELECT CONCAT(jmeno_uzivatele,' ',prijmeni_uzivatele,' (',login_uzivatele,')')
      FROM uzivatele_hodnoty
      WHERE jmeno_uzivatele != '' AND prijmeni_uzivatele != '' AND id_uzivatele != $1
    ", [$this->ubytovany->id()]);
        while ($u = mysqli_fetch_row($o)) {
            $a[] = $u[0];
        }

        return json_encode($a);
    }

    public function kratkyPopis(string $oddelovacDalsihoRadku = '<br>'): string
    {
        $dnyPoTypech = [];
        foreach ($this->ubytovanPoDnech as $cisloDne => $typy) { // typy _v daný den_
            $typVzor = reset($typy);
            foreach ($this->mozneTypy as $typ => $rozsah) {
                if ($this->ubytovan($cisloDne, $typ)) {
                    $poziceZaPosledniMezerou = strrpos($typVzor['nazev'], ' ') + 1;
                    $nazevDne                = mb_strtolower(substr($typVzor['nazev'], $poziceZaPosledniMezerou));
                    $zkratkaDne              = mb_substr($nazevDne, 0, 2);
                    $dnyPoTypech[$typ][]     = $zkratkaDne;
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
