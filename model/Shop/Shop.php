<?php

namespace Gamecon\Shop;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\FiltrAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Jidlo;
use Gamecon\Pravo;
use Gamecon\Shop\SqlStruktura\PredmetSqlStruktura as Sql;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\Cenik;
use Gamecon\XTemplate\XTemplate;
use Uzivatel;

/**
 * Třída starající se o e-shop, nákupy, formy a související
 */
class Shop
{
    // TYPY PŘEDMĚTŮ
    public const PREDMET           = TypPredmetu::PREDMET;
    public const UBYTOVANI         = TypPredmetu::UBYTOVANI;
    public const TRICKO            = TypPredmetu::TRICKO;
    public const JIDLO             = TypPredmetu::JIDLO;
    public const VSTUPNE           = TypPredmetu::VSTUPNE;
    public const PARCON            = TypPredmetu::PARCON;
    public const PROPLACENI_BONUSU = TypPredmetu::PROPLACENI_BONUSU;

    // STAVY PŘEDMĚTŮ
    public const STAV_MIMO        = StavPredmetu::MIMO;
    public const STAV_VEREJNY     = StavPredmetu::VEREJNY;
    public const STAV_PODPULTOVY  = StavPredmetu::PODPULTOVY;
    public const STAV_POZASTAVENY = StavPredmetu::POZASTAVENY;

    public const PN_JIDLO      = 'cShopJidlo';          // post proměnná pro jídlo
    public const PN_JIDLO_ZMEN = 'cShopJidloZmen';      // post proměnná indikující, že se má jídlo aktualizovat

    /** https://cs.wikipedia.org/wiki/Gama_korekce pro nelineární rozsah vstupneho */

    private static $skoly = [
        'UK Univerzita Karlova Praha',
        'MU Masarykova univerzita Brno',
        'VUT Vysoké učení technické Brno',
        'VŠE Vysoká škola ekonomická Praha',
        'ČVUT České vysoké učení technické Praha',
        'VŠB-TU Vysoká škola báňská-Technická univerzita Ostrava',
        'ZU Západočeská univerzita v Plzni',
        'UP Univerzita Palackého v Olomouci',
        'ČZU Česká zemědělská univerzita v Praze',
        'MENDELU Mendelova zemědělská a lesnická univerzita v Brně',
        'UTB Univerzita Tomáše Bati ve Zlíně',
        'JU Jihočeská univerzita v Českých Budějovicích',
        'Univerzita Pardubice',
        'TU Technická univerzita v Liberci',
        'UJEP Univerzita J. E. Purkyně v Ústí nad Labem',
        'Univerzita Hradec Králové',
        'SU Slezská univerzita v Opavě',
        'VŠO Vysoká škola obchodní v Praze',
        'UJAK Univerzita Jana Amose Komenského',
        'VŠCHT Vysoká škola chemicko-technologická v Praze',
    ];

    private static $dny = ['středa', 'čtvrtek', 'pátek', 'sobota', 'neděle'];

    /**
     * @param Uzivatel[] $uzivatele
     * @param string|int $typ
     * @return void
     * @throws \DbException
     */
    public static function zrusObjednavkyPro(
        array $uzivatele,
              $typ,
    ) {
        $povoleneTypy = [self::PREDMET, self::UBYTOVANI, self::TRICKO, self::JIDLO];
        if (!in_array($typ, $povoleneTypy)) {
            throw new \Exception('Tento typ objednávek není možné hromadně zrušit');
        }

        $ids = array_map(static function (
            $u,
        ) {
            return $u->id();
        }, $uzivatele);

        dbQuery(<<<SQL
DELETE sn
FROM shop_nakupy sn
JOIN shop_predmety_s_typem sp ON sp.id_predmetu = sn.id_predmetu AND sp.typ = $0
WHERE sn.id_uzivatele IN ($1) AND sn.rok = $2
SQL,
            [0 => $typ, 1 => $ids, 2 => ROCNIK],
        );
    }

    /** Smaže z názvu identifikaci dne */
    public static function bezDne(string $nazev): string
    {
        $re = ' ?pondělí| ?úterý| ?středa| ?čtvrtek| ?pátek| ?sobota| ?neděle';

        return preg_replace('@' . $re . '@', '', $nazev);
    }

    /**
     * @return Polozka[]
     * @throws \DbException
     */
    public static function letosniPolozky(
        int    $rok = ROCNIK,
        ?array $idckaPolozek = null,
    ): array {
        $polozkyData = dbFetchAll(<<<SQL
SELECT id_predmetu,nazev,cena_aktualni,suma,model_rok,naposledy_koupeno_kdy,prodano_kusu,kusu_vyrobeno,typ,podtyp,nabizet_do,stav
FROM (
    SELECT predmety.id_predmetu,
           TRIM(predmety.nazev) AS nazev,
           predmety.cena_aktualni,
           SUM(nakupy.cena_nakupni) AS suma,
           predmety.model_rok,
           MAX(nakupy.datum) AS naposledy_koupeno_kdy,
           COUNT(nakupy.id_predmetu) AS prodano_kusu,
           predmety.kusu_vyrobeno,
           predmety.typ,
           predmety.podtyp,
           predmety.nabizet_do,
           predmety.ubytovani_den,
           predmety.stav
    FROM shop_predmety_s_typem AS predmety
    LEFT JOIN shop_nakupy AS nakupy
        ON predmety.id_predmetu = nakupy.id_predmetu
            AND nakupy.rok = $0
    WHERE model_rok = $0
        AND IF($3, TRUE, predmety.id_predmetu IN ($2))
    GROUP BY predmety.id_predmetu, predmety.typ, predmety.ubytovani_den, predmety.nazev
) AS seskupeno
ORDER BY typ, IF(typ = $1, LEFT(TRIM(nazev), LOCATE(' ',nazev) - 1), nazev), ubytovani_den
SQL,
            [0 => $rok, 1 => TypPredmetu::UBYTOVANI, 2 => $idckaPolozek, 3 => $idckaPolozek === null],
        );
        $polozky = [];
        foreach ($polozkyData as $polozkaData) {
            $polozky[] = new Polozka($polozkaData);
        }

        return $polozky;
    }

    /**
     * @param SystemoveNastaveni $systemoveNastaveni
     * @return Polozka[]
     */
    public static function letosniPolozkySeSpatnymKoncem(SystemoveNastaveni $systemoveNastaveni): array
    {
        $typJidlo = TypPredmetu::JIDLO;
        $typPredmet = TypPredmetu::PREDMET;
        $typTricko = TypPredmetu::TRICKO;
        $podtypMikina = PodtypPredmetu::MIKINA;

        $idckaPredmetu = dbFetchColumn(<<<SQL
SELECT id_predmetu
FROM shop_predmety_s_typem
WHERE model_rok = {$systemoveNastaveni->rocnik()}
    AND nabizet_do IS NOT NULL
    AND typ IN ($typJidlo, $typPredmet, $typTricko)
    AND CASE
        WHEN typ = {$typJidlo} THEN nabizet_do != $2
        WHEN typ = {$typTricko} THEN nabizet_do != $3
        WHEN typ = {$typPredmet} AND podtyp = $4 THEN nabizet_do != $5
        WHEN typ = {$typPredmet} THEN nabizet_do != $6
        ELSE FALSE
    END
SQL,
            [
                2 => $systemoveNastaveni->prodejJidlaDo(),
                3 => $systemoveNastaveni->prodejTricekDo(),
                4 => $podtypMikina,
                5 => $systemoveNastaveni->prodejMikinDo(),
                6 => $systemoveNastaveni->prodejPredmetuBezTricekDo(),
            ],
        );

        return self::letosniPolozky($systemoveNastaveni->rocnik(), $idckaPredmetu);
    }

    private Cenik $cenik;                     // instance ceníku
    // případné spec. chování shopu
    private               $nastaveni     = [
        'ubytovaniBezZamku' => false,   // ignorovat pozastavení objednávek u ubytování
        'jidloBezZamku'     => false,       // ignorovat pozastavení objednávek u jídla
    ];
    public array          $ubytovaniPole = [];
    public ?ShopUbytovani $ubytovani     = null;
    private               $tricka           = [];
    private               $mikiny           = [];
    private               $predmety         = [];
    private               $predmetyHlavni   = [];
    private               $predmetyVedlejsi = [];
    private               $jidlo            = [];
    private               $ubytovaniOd;
    private               $ubytovaniDo;
    private               $ubytovaniTypy = [];
    private               $vstupne       = ['sum_cena_nakupni' => 0., 'id_predmetu' => null /*Před začátkem prodejů musí být vstupné naimportováno (typ VSTUPNE)*/];                   // dobrovolné vstupné (složka zaplacená regurélně včas)
    private               $vstupnePozde  = ['sum_cena_nakupni' => 0.0, 'id_predmetu' => null/*Před začátkem prodejů musí být dobrovolné vstupné naimportováno (typ VSTUPNE, v názvu "pozdě")*/];                  // dobrovolné vstupné (složka zaplacená pozdě)
    private               $vstupneJeVcas;                                                // jestli se dobrovolné vstupné v tento okamžik chápe jako zaplacené včas
    private               $klicU         = 'shopU';                                      // klíč formu pro identifikaci polí
    private               $klicUPokoj    = 'shopUPokoj';                                 // s kým chce být na pokoji
    private               $klicV         = 'shopV';                                      // klíč formu pro identifikaci vstupného
    private               $klicP         = 'shopP';                                      // klíč formu pro identifikaci polí
    private               $klicT         = 'shopT';                                      // klíč formu pro identifikaci polí s tričkama
    private               $klicM         = 'shopM';                                      // klíč formu pro identifikaci polí s mikinami
    private               $klicS         = 'shopS';                                      // klíč formu pro identifikaci polí se slevami

    public function __construct(
        private readonly Uzivatel           $zakaznik,
        private readonly Uzivatel           $objednatel,
        private readonly SystemoveNastaveni $systemoveNastaveni,
        array                               $nastaveni = null,
    ) {
        if (is_array($nastaveni)) {
            $this->nastaveni = array_replace($this->nastaveni, $nastaveni);
        }

        $mimo = StavPredmetu::MIMO;
        $rocnik = $this->systemoveNastaveni->rocnik();
        $zakaznikId = $this->zakaznik->id();

        // vybrat všechny předměty pro tento rok + předměty v nabídce + předměty, které si koupil
        $results = dbFetchAll(
            <<<SQL
            SELECT *
            FROM (
                  SELECT
                    predmety.id_predmetu, predmety.model_rok, predmety.cena_aktualni, predmety.stav,
                    predmety.nabizet_do, predmety.kusu_vyrobeno, predmety.typ, predmety.podtyp, predmety.ubytovani_den, predmety.popis, predmety.vedlejsi, predmety.kod_predmetu,
                    IF(predmety.model_rok = {$rocnik} OR COALESCE(predmety.popis, '') = '', predmety.nazev, CONCAT(predmety.nazev, ' (', predmety.popis, ')')) AS nazev,
                    COUNT(IF(nakupy.rok = {$rocnik}, 1, NULL)) AS kusu_prodano,
                    COUNT(IF(nakupy.id_uzivatele = {$zakaznikId} AND nakupy.rok = {$rocnik}, 1, NULL)) AS kusu_uzivatele,
                    SUM(IF(nakupy.id_uzivatele = {$zakaznikId} AND nakupy.rok = {$rocnik}, nakupy.cena_nakupni, 0)) AS sum_cena_nakupni,
                    MAX(nakupy.cena_nakupni) AS cena_nakupni
                  FROM shop_predmety_s_typem predmety
                  LEFT JOIN shop_nakupy AS nakupy
                    ON predmety.id_predmetu = nakupy.id_predmetu
                    AND nakupy.rok = {$rocnik}
                  WHERE predmety.model_rok = {$rocnik}
                    AND (predmety.stav > {$mimo} OR nakupy.rok = {$rocnik})
                  GROUP BY predmety.id_predmetu
            ) AS seskupeno
            ORDER BY typ, ubytovani_den, nazev, id_predmetu
            SQL,
        );

        //inicializace
        $this->jidlo['dny'] = [];
        $this->jidlo['druhy'] = [];

        foreach ($results as $r) {
            $typ = $r['typ'];
            if ($typ == self::PROPLACENI_BONUSU) {
                continue; // není určeno k přímému prodeji
            }
            unset($fronta); // $fronta reference na frontu kam vložit předmět (nelze dát =null, přepsalo by předchozí vrch fronty)
            if ($typ != self::UBYTOVANI && $r['nabizet_do'] && strtotime($r['nabizet_do']) < time()) {
                $r['stav'] = StavPredmetu::POZASTAVENY;
            }
            $r['nabizet'] = $r['stav'] == StavPredmetu::VEREJNY; // v základu nabízet vše v stavu 1
            // rozlišení kam ukládat a jestli nabízet podle typu
            if ($typ == self::PREDMET) {
                if (($r[Sql::PODTYP] ?? null) === PodtypPredmetu::MIKINA) {
                    $fronta = &$this->mikiny[];
                } else {
                    $fronta = &$this->predmety[];
                }
            } elseif ($typ == self::JIDLO) {
                $den = $r['ubytovani_den'];
                $druh = trim(self::bezDne($r['nazev']));
                if (!empty($this->jidlo['jidla'][$den][$druh]['kusu_uzivatele'])) {
                    /*
                     * Speciální případ, kdy existuje více verzí stejného jídla ve stejném roce.
                     * Například v roce 2022 jsme prodávali teplé jídlo nejdříve za 100 korun, pak ale dodavatel zjistil,
                     * že ceny surovin jdou nahrodu tak prudce, že musí zdražit na 120.-
                     * Ceny jsme zvýšili až potom, co si někteří účastníci stihli objednat jídlo za nižší cenu.
                     * V takovém případě chceme účastníkovi zobrazovat tu instanci jídla, kterou si už objednal (za nižší cenu).
                     */
                    $this->jidlo['jidla'][$den][$druh]['stav'] = $r['stav']; // chceme povolit změnu jídla, pokud nová verze (za novou cenu) je prodejná
                    continue;
                }
                $r['nabizet'] = $r['nabizet'] || ($r['stav'] == StavPredmetu::POZASTAVENY && $this->nastaveni['jidloBezZamku']);
                if ($r['kusu_uzivatele'] > 0) {
                    $this->jidlo['jidloObednano'][$r['id_predmetu']] = true;
                }
                if ($r['kusu_uzivatele'] || $r['nabizet']) {
                    //zobrazení jen dnů / druhů, které mají smysl
                    $this->jidlo['dny'][$den] = true;
                    $this->jidlo['druhy'][$druh] = true;
                }
                $fronta = &$this->jidlo['jidla'][$den][$druh];
            } elseif ($typ == self::UBYTOVANI) {
                $r['nabizet'] = true;
                /** protože se to řeší v @see ShopUbytovani::totoUbytovaniVyrazeno
                 */
                $fronta = &$this->ubytovaniPole[];
            } elseif ($typ == self::TRICKO) {
                $smiModre = $this->zakaznik->maPravo(Pravo::MUZE_OBJEDNAVAT_MODRA_TRICKA);
                $smiCervene = $this->zakaznik->maPravo(Pravo::MUZE_OBJEDNAVAT_CERVENA_TRICKA);
                $r['nabizet'] = (
                    $r['nabizet']
                    || ($r['stav'] == self::STAV_PODPULTOVY && mb_stripos($r['nazev'], 'modré') !== false && $smiModre)
                    || ($r['stav'] == self::STAV_PODPULTOVY && mb_stripos($r['nazev'], 'červené') !== false && $smiCervene)
                );
                $fronta = &$this->tricka[];
            } elseif ($typ == self::VSTUPNE) {
                if (!str_contains($r['nazev'], 'pozdě')) {
                    $this->vstupne = $r;
                    $this->vstupneJeVcas = $r['stav'] == self::STAV_PODPULTOVY;
                } else {
                    $this->vstupnePozde = $r;
                }
            } else {
                throw new \Exception('Objevil se nepodporovaný typ předmětu s č.' . var_export($r['typ'], true));
            }
            // finální uložení předmětu na vrchol dané fronty
            $fronta = $r;
        }

        $this->jidlo = $this->seradJidla($this->jidlo);

        // Rozdělení předmětů na hlavní a vedlejší
        foreach ($this->predmety as $predmet) {
            if ($predmet[Sql::VEDLEJSI]) {
                $this->predmetyVedlejsi[] = $predmet;
            } else {
                $this->predmetyHlavni[] = $predmet;
            }
        }

        $this->ubytovani = new ShopUbytovani(
            $this->ubytovaniPole,
            $this->zakaznik,
            $this->objednatel,
            KontextZobrazeni::vytvorZGlobals(),
            $systemoveNastaveni,
        ); // náhrada reprezentace polem za objekt
    }

    private function cenik(): Cenik
    {
        if (($this->cenik ?? null) === null) {
            $this->cenik = new Cenik(
                $this->zakaznik,
                $this->zakaznik->finance(),
                $this->systemoveNastaveni,
            );
        }

        return $this->cenik;
    }

    private function seradJidla(array $jidla): array
    {

        uksort($jidla['druhy'], [$this, 'seradDruhyJidel']);
        if (array_key_exists("jidla", $jidla) && $jidla['jidla'] != null) {
            foreach ($jidla['jidla'] as &$jidlaJedenDen) {
                uksort($jidlaJedenDen, [$this, 'seradDruhyJidel']);
            }
        }

        return $jidla;
    }

    private function seradDruhyJidel(
        string $nejakyDruh,
        string $jinyDruh,
    ): int {
        return Jidlo::dejPoradiJidlaBehemDne($nejakyDruh) <=> Jidlo::dejPoradiJidlaBehemDne($jinyDruh);
    }

    public function ubytovani(): ShopUbytovani
    {
        return $this->ubytovani;
    }

    private static function denNazev($cislo)
    {
        return self::$dny[$cislo];
    }

    public function jidloObjednatelneDoHtml(): string
    {
        return $this->systemoveNastaveni->prodejJidlaDo()->format('j. n.');
    }

    public function objednaneJidloPrehledHtml(): string
    {
        $t = new XTemplate(__DIR__ . '/templates/shop-jidla-prehled.xtpl');

        // inicializace
        $druhy = $this->jidlo['druhy'];
        ksort($druhy);
        $dny = $this->jidlo['dny'];
        $jidla = $this->jidlo['jidla'];

        // vykreslení
        foreach (array_keys($druhy) as $druh) {
            foreach (array_keys($dny) as $den) {
                $jidlo = $jidla[$den][$druh] ?? null;
                if ($jidlo && $jidlo['kusu_uzivatele']) {
                    $t->assign('nazev', $jidlo['nazev']);
                    $t->parse('jidla.jidlo');
                }
            }
        }

        $t->parse('jidla');

        return $t->text('jidla');
    }

    public function koupilNejakouVec(): bool
    {
        return $this->koupilNejakyPredmet() || $this->koupilNejakeTricko();
    }

    /**
     * Textový souhrn všech objednávek zákazníka (ubytování, jídlo, trička,
     * mikiny, předměty) pro notifikaci infopultu při odhlášení z GC.
     * @return string[] pole řádků; prázdné, pokud nic neobjednal
     */
    public function prehledObjednavekProInfopult(): array
    {
        $radky = [];

        if ($ubytovani = $this->ubytovani()->objednaneUbytovaniNazvy()) {
            $radky[] = 'Ubytování: ' . implode(', ', $ubytovani);
        }

        $jidla = [];
        foreach ($this->objednanaJidlaDleDnu() as $den) {
            foreach ($den['jidla'] as $jidlo) {
                $jidla[] = $jidlo['nazev'];
            }
        }
        if ($jidla) {
            $radky[] = 'Jídlo: ' . implode(', ', $jidla);
        }

        if ($tricka = $this->nazvyObjednanychVeci($this->tricka)) {
            $radky[] = 'Trička: ' . implode(', ', $tricka);
        }
        if ($mikiny = $this->nazvyObjednanychVeci($this->mikiny)) {
            $radky[] = 'Mikiny: ' . implode(', ', $mikiny);
        }
        if ($predmety = $this->nazvyObjednanychVeci($this->predmety)) {
            $radky[] = 'Předměty: ' . implode(', ', $predmety);
        }

        return $radky;
    }

    /**
     * @param array $veci fronta předmětů ze Shopu (trička / mikiny / předměty)
     * @return string[] názvy těch, které si zákazník objednal (s počtem kusů, je-li > 1)
     */
    private function nazvyObjednanychVeci(array $veci): array
    {
        $nazvy = [];
        foreach ($veci as $vec) {
            $pocet = (int)($vec['kusu_uzivatele'] ?? 0);
            if ($pocet > 0) {
                $nazvy[] = $pocet > 1
                    ? "{$vec['nazev']} ({$pocet}×)"
                    : $vec['nazev'];
            }
        }

        return $nazvy;
    }

    public function koupilNejakyPredmet(): bool
    {
        foreach ($this->predmety as $predmet) {
            if ($predmet['kusu_uzivatele'] > 0) {
                return true;
            }
        }

        foreach ($this->mikiny as $mikina) {
            if ($mikina['kusu_uzivatele'] > 0) {
                return true;
            }
        }

        return false;
    }

    public function koupilNejakeTricko(): bool
    {
        foreach ($this->tricka as $tricko) {
            if ($tricko['kusu_uzivatele'] > 0) {
                return true;
            }
        }

        return false;
    }

    public function objednalNejakeJidlo(): bool
    {
        foreach ($this->jidlo['jidloObednano'] ?? [] as $nejakyTypJidlaJeObjednany) {
            if ($nejakyTypJidlaJeObjednany) {
                return true;
            }
        }

        return false;
    }

    /**
     * Objednaná jídla seskupená po dnech (středa … neděle) v pořadí.
     * Slouží pro výběr dnů a konkrétních jídel při tisku stravenek na infopultu.
     * Klíč `jidla` obsahuje `nazev` (plný název včetně dne, např. „Oběd středa“ – shodný
     * s predmety.nazev a tedy použitelný jako filtr reportu) a `druh` (druh jídla bez dne, např. „Oběd“).
     * @return array<int, array{den: string, jidla: array<int, array{nazev: string, druh: string}>}>
     */
    public function objednanaJidlaDleDnu(): array
    {
        $dny = [];
        foreach ($this->jidlo['jidla'] ?? [] as $den => $druhyVDen) {
            $jidlaVDen = [];
            foreach ($druhyVDen as $druh => $jidloVDen) {
                if (($jidloVDen['kusu_uzivatele'] ?? 0) > 0) {
                    $jidlaVDen[] = ['nazev' => $jidloVDen['nazev'], 'druh' => $druh];
                }
            }
            if ($jidlaVDen) {
                $dny[$den] = ['den' => self::denNazev($den), 'jidla' => $jidlaVDen];
            }
        }
        ksort($dny);

        return array_values($dny);
    }

    public function trickaObjednatelnaDoHtml(): string
    {
        return $this->systemoveNastaveni->prodejTricekDo()->format('j. n.');
    }

    public function mikinyObjednatelnaDoHtml(): string
    {
        return $this->systemoveNastaveni->prodejMikinDo()->format('j. n.');
    }

    public function predmetyBezTricekObjednatelneDoHtml(): string
    {
        return $this->systemoveNastaveni->prodejPredmetuBezTricekDo()->format('j. n.');
    }

    public function koupeneVeciPrehledHtml()
    {
        $t = new XTemplate(__DIR__ . '/templates/shop-predmety-prehled.xtpl');

        foreach ($this->predmety as $predmet) {
            if ($predmet['kusu_uzivatele'] <= 0) {
                continue;
            }
            $t->assign([
                'nazev'          => $predmet['nazev'],
                'kusu_uzivatele' => $predmet['kusu_uzivatele'],
            ]);
            $t->parse('predmety.predmet');
        }

        foreach ($this->tricka as $tricko) {
            if ($tricko['kusu_uzivatele'] <= 0) {
                continue;
            }
            $t->assign([
                'nazev'          => $tricko['nazev'],
                'kusu_uzivatele' => $tricko['kusu_uzivatele'],
            ]);
            $t->parse('predmety.predmet');
        }

        foreach ($this->mikiny as $mikina) {
            if ($mikina['kusu_uzivatele'] <= 0) {
                continue;
            }
            $t->assign([
                'nazev'          => $mikina['nazev'],
                'kusu_uzivatele' => $mikina['kusu_uzivatele'],
            ]);
            $t->parse('predmety.predmet');
        }

        $t->parse('predmety');

        return $t->text('predmety');
    }

    /**
     * Jestli je toto prvním nákupem daného uživatele
     */
    private function prvniNakup()
    {
        return !$this->zakaznik->gcPrihlasen();
    }

    public function ubytovaniObjednatelneDoHtml(): string
    {
        return $this->systemoveNastaveni->prodejUbytovaniDo()->format('j. n.');
    }

    /**
     * Upraví objednávku z pole id $stare na pole $nove
     * @param array<int|string> $stare
     * @param array<int|string> $nove
     */
    private function zmenObjednavku(
        array $stare,
        array $nove,
    ): void {
        $nechce = array_diff($stare, $nove);
        $chceNove = array_diff($nove, $stare);
        // přírustky
        foreach ($chceNove as $noveId) {
            $this->prodat((int)$noveId, 1, false);
        }
        // mazání
        if ($nechce) {
            dbQueryS(
                'DELETE FROM shop_nakupy WHERE id_uzivatele = $1 AND rok = $2 AND id_predmetu IN($3)',
                [$this->zakaznik->id(), $this->systemoveNastaveni->rocnik(), $nechce],
            );
        }
    }

    public function zrusNakupPredmetu(
        $idPredmetu,
        int $pocet,
    ): int {
        $idPredmetu = (int)$idPredmetu;
        $rok = ROCNIK;
        $query = <<<SQL
            DELETE FROM shop_nakupy
            WHERE id_uzivatele={$this->zakaznik->id()}
            AND id_predmetu = $idPredmetu
            AND rok=$rok
        SQL;
        if ($pocet > 0) {
            $query .= <<<SQL
                -- pozor musi byt aspon jeden bily znak, treba novy radek
                LIMIT $pocet
            SQL;
        }
        $mysqli = dbQuery($query);

        return dbAffectedOrNumRows($mysqli);
    }

    /** Zpracuje formulář s jídlem */
    public function zpracujJidlo(): void
    {
        if (!isset($_POST[self::PN_JIDLO_ZMEN])) {
            return;
        }
        $ma = array_keys($this->jidlo['jidloObednano'] ?? []);
        $chce = array_keys(post(self::PN_JIDLO)
            ?: []);

        $dnyHotelovychPokoju = $this->ubytovani->dnyHotelovychPokoju();
        if ($dnyHotelovychPokoju) {
            // ubytování den N (noc) → snídaně den N+1 (ráno)
            $dnySnidaniHotelu = array_map(fn(int $den) => $den + 1, $dnyHotelovychPokoju);
            $chce = array_filter($chce, function ($idPredmetu) use ($dnySnidaniHotelu) {
                $jidla = $this->jidlo['jidla'] ?? [];
                foreach ($jidla as $den => $druhy) {
                    foreach ($druhy as $druh => $jidlo) {
                        if ((int)$jidlo['id_predmetu'] === (int)$idPredmetu
                            && Jidlo::jeToSnidane($druh)
                            && in_array((int)$den, $dnySnidaniHotelu, true)
                        ) {
                            return false;
                        }
                    }
                }
                return true;
            });
        }

        $this->zmenObjednavku($ma, $chce);

        // pojistka: smazat snídaně v ceně hotelu i po zpracování jídla,
        // pro případ že by se nějaká proklouzla (JS selhání, obejití formuláře apod.)
        ShopUbytovani::zrusSnidaneProHotelovePokoje($this->zakaznik);
    }

    private function cenaVybraneOpakovaneVybiranePolozky(array $polozky, int $idPredmetu): ?float
    {
        if ($idPredmetu === 0) {
            return null;
        }
        foreach ($polozky as $polozka) {
            if ((int)$polozka[Sql::ID_PREDMETU] === $idPredmetu) {
                return (float)$polozka[Sql::CENA_AKTUALNI];
            }
        }

        return null;
    }

    private function vychoziCenaOpakovaneVybiranePolozky(array $polozky): string
    {
        $ceny = array_map(
            static fn(array $polozka): int => (int)round((float)$polozka[Sql::CENA_AKTUALNI]),
            array_filter(
                $polozky,
                static fn(array $polozka): bool => (bool)$polozka['nabizet'],
            ),
        );
        $ceny = array_values(array_unique($ceny));
        sort($ceny);

        if (!$ceny) {
            return '';
        }
        if (count($ceny) === 1) {
            return $this->cenaOpakovaneVybiranePolozkyHtml((float)$ceny[0]);
        }

        return reset($ceny) . '-' . end($ceny) . '&thinsp;Kč';
    }

    private function cenaOpakovaneVybiranePolozkyHtml(float $cena): string
    {
        return round($cena) . '&thinsp;Kč';
    }

    public function dejPopisUbytovani(): string
    {
        return $this->ubytovani->kratkyPopis();
    }

    public function zrusLetosniObjednaneUbytovani(string $zdrojZruseni): int
    {
        return $this->zrusLetosniObjednavkyTypu(TypPredmetu::UBYTOVANI, $zdrojZruseni);
    }

    private function zrusLetosniObjednavkyTypu(
        int    $typPredetu,
        string $zdrojZruseni,
    ): int {
        $insertResult = dbQuery(<<<SQL
            INSERT INTO shop_nakupy_zrusene(id_nakupu, id_uzivatele, id_predmetu, rocnik, cena_nakupni, datum_nakupu, datum_zruseni, zdroj_zruseni)
            SELECT nakupy.id_nakupu, nakupy.id_uzivatele, nakupy.id_predmetu, nakupy.rok, nakupy.cena_nakupni, nakupy.datum, $0, $1
            FROM shop_nakupy AS nakupy
            JOIN shop_predmety_s_typem AS predmety ON nakupy.id_predmetu = predmety.id_predmetu
            WHERE nakupy.rok = {$this->systemoveNastaveni->rocnik()}
              AND nakupy.id_uzivatele = {$this->zakaznik->id()}
              AND predmety.typ = {$typPredetu}
            SQL,
            [
                0 => $this->systemoveNastaveni->ted()->format(DateTimeCz::FORMAT_DB),
                1 => $zdrojZruseni,
            ],
        );
        if (dbAffectedOrNumRows($insertResult) === 0) {
            return 0;
        }

        $deleteResult = dbQuery(<<<SQL
            DELETE nakupy.*
            FROM shop_nakupy AS nakupy
            JOIN shop_predmety_s_typem AS predmety ON nakupy.id_predmetu = predmety.id_predmetu
            WHERE nakupy.rok = {$this->systemoveNastaveni->rocnik()}
              AND nakupy.id_uzivatele = {$this->zakaznik->id()}
              AND predmety.typ = {$typPredetu}
            SQL,
        );

        return dbAffectedOrNumRows($deleteResult);
    }

    /**
     * Zruší letošní objednávky zákazníka (při odhlášení z GC) – ale jen ty, které
     * ještě lze zrušit. Po uzávěrce jídla / ubytování už je GameCon objednal
     * u dodavatele (catering, ubytovatel) a nedostane za ně zpět peníze, takže
     * tyto položky se NEruší a zůstávají zákazníkovi naúčtované.
     * @return int počet skutečně zrušených nákupů
     */
    public function zrusZrusitelneLetosniObjednavky(string $zdrojZruseni): int
    {
        // typy předmětů, které se po své uzávěrce už neruší (zůstávají naúčtované)
        $typyKZachovani = [];
        if ($this->systemoveNastaveni->prodejJidlaUkoncen()) {
            $typyKZachovani[] = self::JIDLO;
        }
        if ($this->systemoveNastaveni->prodejUbytovaniUkoncen()) {
            $typyKZachovani[] = self::UBYTOVANI;
        }
        // pozn.: prázdné pole neřešíme přes NOT IN (NULL) – to by (kvůli SQL NULL) vyloučilo
        // úplně všechno; místo toho podmínku vůbec nepřidáváme.
        $podminkaZachovani = $typyKZachovani
            ? 'AND shop_nakupy.id_predmetu NOT IN (
                    SELECT id_predmetu FROM shop_predmety_s_typem WHERE typ IN (' . implode(', ', array_map('intval', $typyKZachovani)) . ')
                )'
            : '';

        $rocnik      = $this->systemoveNastaveni->rocnik();
        $idZakaznika = $this->zakaznik->id();

        dbQuery(<<<SQL
            INSERT INTO shop_nakupy_zrusene(id_nakupu, id_uzivatele, id_predmetu, rocnik, cena_nakupni, datum_nakupu, datum_zruseni, zdroj_zruseni)
            SELECT id_nakupu, id_uzivatele, id_predmetu, rok, cena_nakupni, datum, $0, $1
            FROM shop_nakupy
            WHERE shop_nakupy.rok = {$rocnik} AND shop_nakupy.id_uzivatele = {$idZakaznika}
            {$podminkaZachovani}
            SQL,
            [0 => $this->systemoveNastaveni->ted()->format(DateTimeCz::FORMAT_DB), $zdrojZruseni],
        );
        $result = dbQuery(<<<SQL
            DELETE FROM shop_nakupy
            WHERE shop_nakupy.rok = {$rocnik} AND shop_nakupy.id_uzivatele = {$idZakaznika}
            {$podminkaZachovani}
            SQL,
        );

        return dbAffectedOrNumRows($result);
    }

    public function zrusPrihlaseniNaLetosniLarpy(
        \Uzivatel $odhlasujici,
        string    $zdrojZruseni,
    ): int {
        $prihlaseneLarpy = Aktivita::zFiltru(
            systemoveNastaveni: $this->systemoveNastaveni,
            filtr: [
                FiltrAktivity::TYP        => TypAktivity::LARP,
                FiltrAktivity::ROK        => $this->systemoveNastaveni->rocnik(),
                FiltrAktivity::PRIHLASENI => [$this->zakaznik->id()],
            ],

        );
        foreach ($prihlaseneLarpy as $prihlasenyLarp) {
            $prihlasenyLarp->odhlas($this->zakaznik, $odhlasujici, $zdrojZruseni, AKtivita::ODEMKNI_TYM_ODHLASENIM);
        }

        return count($prihlaseneLarpy);
    }

    public function zrusPrihlaseniNaLetosniRpg(
        \Uzivatel $odhlasujici,
        string    $zdrojZruseni,
    ): int {
        $prihlasenaRpg = Aktivita::zFiltru(
            systemoveNastaveni: $this->systemoveNastaveni,
            filtr: [
                FiltrAktivity::TYP        => TypAktivity::RPG,
                FiltrAktivity::ROK        => $this->systemoveNastaveni->rocnik(),
                FiltrAktivity::PRIHLASENI => [$this->zakaznik->id()],
            ],
        );
        foreach ($prihlasenaRpg as $prihlaseneRpg) {
            $prihlaseneRpg->odhlas($this->zakaznik, $odhlasujici, $zdrojZruseni, AKtivita::ODEMKNI_TYM_ODHLASENIM);
        }

        return count($prihlasenaRpg);
    }

    public function zrusPrihlaseniNaVsechnyAktivity(
        \Uzivatel $odhlasujici,
        string    $zdrojZruseni,
    ): int {
        $prihlaseneAktivity = Aktivita::zFiltru(
            systemoveNastaveni: $this->systemoveNastaveni,
            filtr: [
                FiltrAktivity::ROK        => $this->systemoveNastaveni->rocnik(),
                FiltrAktivity::PRIHLASENI => [$this->zakaznik->id()],
            ],
        );
        foreach ($prihlaseneAktivity as $prihlasenaAktivita) {
            $prihlasenaAktivita->odhlas($this->zakaznik, $odhlasujici, $zdrojZruseni, AKtivita::ODEMKNI_TYM_ODHLASENIM);
        }

        return count($prihlaseneAktivity);
    }

    /**
     * @param string $zdrojZruseni
     * @param int|null $rocnik
     * @return string[]
     */
    public function dejNazvyZrusenychNakupu(
        string $zdrojZruseni,
        int    $rocnik = null,
    ): array {
        $rocnik ??= $this->systemoveNastaveni->rocnik();

        return dbFetchColumn(<<<SQL
            SELECT shop_predmety.nazev
            FROM shop_predmety
            JOIN shop_nakupy_zrusene ON shop_predmety.id_predmetu = shop_nakupy_zrusene.id_predmetu
            WHERE shop_nakupy_zrusene.zdroj_zruseni = $0
                AND shop_nakupy_zrusene.id_uzivatele = {$this->zakaznik->id()}
                AND shop_nakupy_zrusene.rocnik = {$rocnik}
            SQL,
            [0 => $zdrojZruseni],
        );
    }

    public function prodat(
        int  $idPredmetu,
        int  $kusu = 1,
        bool $vcetneOznamemi = false,
    ) {
        dbBegin();
        try {
            // Lock the base-table row first; model_rok is then read from the view (virtual column derived from archived_at).
            $predmet = dbOneLine(
                "SELECT cena_aktualni, kusu_vyrobeno, nazev FROM shop_predmety WHERE id_predmetu = $0 FOR UPDATE",
                [0 => $idPredmetu],
            );
            if ($predmet) {
                $predmet['model_rok'] = dbOneCol(
                    "SELECT model_rok FROM shop_predmety_s_typem WHERE id_predmetu = $0",
                    [0 => $idPredmetu],
                );
            }
            if (!$predmet) {
                throw new \Chyba("Předmět s ID {$idPredmetu} neexistuje.");
            }
            $aktualniRocnik = $this->systemoveNastaveni->rocnik();
            if ((int)$predmet['model_rok'] !== $aktualniRocnik) {
                throw new \Chyba("Předmět '{$predmet['nazev']}' patří do ročníku {$predmet['model_rok']}, nelze ho prodávat v ročníku {$aktualniRocnik}.");
            }
            $cenaAktualni = $predmet['cena_aktualni'];

            if ($predmet['kusu_vyrobeno'] !== null) {
                $prodanoKusu = (int) dbOneCol(
                    "SELECT COUNT(*) FROM shop_nakupy WHERE id_predmetu = $0 AND rok = $1",
                    [0 => $idPredmetu, 1 => $aktualniRocnik],
                );
                $zbyvajiciKusu = max(0, (int) $predmet['kusu_vyrobeno'] - $prodanoKusu);
                if ($kusu > $zbyvajiciKusu) {
                    throw new \Chyba("Předmět '{$predmet['nazev']}' už nejde objednat v požadovaném počtu. Zbývá dostupných kusů: {$zbyvajiciKusu}.");
                }
            }

            // Vlastní objednávka na každý prodej — drží pohromadě řádky nákupu a jejich
            // protizápis v platbách. Bez ní by nové nákupy zůstaly bez order_id, které
            // historické řádky mají z migrace.
            dbQuery(
                'INSERT INTO shop_order (customer_id, year, status, total_price, created_at, completed_at, accommodation_declined)
                 VALUES ($0, $1, $2, $3, NOW(), NOW(), 0)',
                [
                    0 => $this->zakaznik->id(),
                    1 => $aktualniRocnik,
                    2 => 'completed',
                    3 => ((float)$cenaAktualni) * $kusu,
                ],
            );
            $idObjednavky = dbInsertId();

            for ($i = 1; $i <= $kusu; $i++) {
                dbQuery(<<<SQL
INSERT INTO shop_nakupy(id_uzivatele,id_objednatele,id_predmetu,rok,cena_nakupni,datum,order_id)
VALUES ({$this->zakaznik->id()},{$this->objednatel->id()},{$idPredmetu},{$aktualniRocnik},{$cenaAktualni},NOW(),{$idObjednavky})
SQL,
                );
            }

            if ($this->zakaznik->id() === Uzivatel::ANONYM) {
                $this->zakaznik->finance()->pripis(
                    ((float)$cenaAktualni) * $kusu,
                    $this->objednatel,
                    'anonymní prodej',
                    idObjednavky: $idObjednavky,
                );
            }
            dbCommit();
        } catch (\Throwable $throwable) {
            dbRollback();
            throw $throwable;
        }

        if (!$vcetneOznamemi) {
            return;
        }

        $yu = '';
        if ($kusu >= 5) {
            $yu = 'ů';
        } elseif ($kusu > 1) {
            $yu = 'y';
        }
        oznameni("Prodáno $kusu kus$yu {$predmet['nazev']}");
    }
}
