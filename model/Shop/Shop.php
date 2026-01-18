<?php

namespace Gamecon\Shop;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\FiltrAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Jidlo;
use Gamecon\Pravo;
use Gamecon\Shop\SqlStruktura\NakupySqlStruktura;
use Gamecon\Shop\SqlStruktura\PredmetSqlStruktura;
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
    private const VSTUPNE_GAMA_KOREKCE = 0.5;

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
JOIN shop_predmety sp ON sp.id_predmetu = sn.id_predmetu AND sp.typ = $0
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
SELECT id_predmetu,nazev,cena_aktualni,suma,model_rok,naposledy_koupeno_kdy,prodano_kusu,kusu_vyrobeno,typ,je_letosni_hlavni,nabizet_do,stav
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
           predmety.je_letosni_hlavni,
           predmety.nabizet_do,
           predmety.ubytovani_den,
           predmety.stav
    FROM shop_predmety AS predmety
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

        $idckaPredmetu = dbFetchColumn(<<<SQL
SELECT id_predmetu
FROM shop_predmety
WHERE model_rok = {$systemoveNastaveni->rocnik()}
    AND nabizet_do IS NOT NULL
    AND typ IN ($typJidlo, $typPredmet, $typTricko)
    AND CASE typ
        WHEN {$typJidlo} THEN nabizet_do != $2
        WHEN {$typTricko} THEN nabizet_do != $3
        WHEN {$typPredmet} THEN nabizet_do != $4
        ELSE FALSE
    END
SQL,
            [
                2 => $systemoveNastaveni->prodejJidlaDo(),
                3 => $systemoveNastaveni->prodejTricekDo(),
                4 => $systemoveNastaveni->prodejPredmetuBezTricekDo(),
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
    private               $tricka        = [];
    private               $predmety      = [];
    private               $jidlo         = [];
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
        $results = $this->systemoveNastaveni->db()->dbFetchAll(
            [
                PredmetSqlStruktura::SHOP_PREDMETY_TABULKA,
                NakupySqlStruktura::SHOP_NAKUPY_TABULKA,
            ],
            <<<SQL
            SELECT *
            FROM (
                  SELECT
                    predmety.id_predmetu, predmety.model_rok, predmety.cena_aktualni, predmety.stav,
                    predmety.nabizet_do, predmety.kusu_vyrobeno, predmety.typ, predmety.ubytovani_den, predmety.popis,
                    IF(predmety.model_rok = {$rocnik} OR COALESCE(predmety.popis, '') = '', predmety.nazev, CONCAT(predmety.nazev, ' (', predmety.popis, ')')) AS nazev,
                    COUNT(IF(nakupy.rok = {$rocnik}, 1, NULL)) AS kusu_prodano,
                    COUNT(IF(nakupy.id_uzivatele = {$zakaznikId} AND nakupy.rok = {$rocnik}, 1, NULL)) AS kusu_uzivatele,
                    SUM(IF(nakupy.id_uzivatele = {$zakaznikId} AND nakupy.rok = {$rocnik}, nakupy.cena_nakupni, 0)) AS sum_cena_nakupni
                  FROM shop_predmety predmety
                  LEFT JOIN shop_nakupy AS nakupy
                    ON predmety.id_predmetu = nakupy.id_predmetu
                    AND nakupy.rok = {$rocnik}
                  WHERE predmety.stav > {$mimo} OR nakupy.rok = {$rocnik}
                  GROUP BY predmety.id_predmetu
            ) AS seskupeno
            -- POZOR, více aktivních ubytování ve stejný den není podporováno, "zvítězí" starší model, protože model_rok DESC a poslední záznam v následujícím foreach přepíše předchozí
            ORDER BY typ, ubytovani_den, nazev, model_rok DESC, id_predmetu
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
            if ($r['nabizet_do'] && strtotime($r['nabizet_do']) < time()) {
                $r['stav'] = StavPredmetu::POZASTAVENY;
            }
            $r['nabizet'] = $r['stav'] == StavPredmetu::VEREJNY; // v základu nabízet vše v stavu 1
            // rozlišení kam ukládat a jestli nabízet podle typu
            if ($typ == self::PREDMET) {
                $fronta = &$this->predmety[];
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
        foreach ($jidla['jidla'] as &$jidlaJedenDen) {
            uksort($jidlaJedenDen, [$this, 'seradDruhyJidel']);
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

    /**
     * Vrátí html kód formuláře s výběrem jídla
     */
    public function jidloHtml(bool $muzeEditovatUkoncenyProdej = false)
    {
        // inicializace
        $dny = $this->jidlo['dny'];
        $druhy = $this->jidlo['druhy'];
        $jidla = $this->jidlo['jidla'] ?? [];
        $prodejJidlaUkoncen = !$muzeEditovatUkoncenyProdej && $this->systemoveNastaveni->prodejJidlaUkoncen();
        $cenik = $this->cenik();
        // vykreslení
        $t = new XTemplate(__DIR__ . '/templates/shop-jidlo.xtpl');
        if (!defined('PRODEJ_JIDLA_POZASTAVEN') || !PRODEJ_JIDLA_POZASTAVEN) {
            foreach (array_keys($druhy) as $druh) {
                foreach (array_keys($dny) as $den) {
                    $jidlo = $jidla[$den][$druh] ?? null;
                    if ($jidlo && ($jidlo['nabizet'] || $jidlo['kusu_uzivatele'])) {
                        $t->assign('selected', $jidlo['kusu_uzivatele'] > 0
                            ? 'checked'
                            : '');
                        $t->assign('pnName', self::PN_JIDLO . '[' . $jidlo['id_predmetu'] . ']');
                        $t->parse($prodejJidlaUkoncen || ($jidlo['stav'] == self::STAV_POZASTAVENY && !$this->nastaveni['jidloBezZamku'])
                            ? 'jidlo.druh.den.locked'
                            : 'jidlo.druh.den.checkbox',
                        );
                    }
                    $t->parse('jidlo.druh.den');
                }
                $t->assign('druh', $druh);
                if ($jidlo !== null) {
                    $vec = $cenik->cena($jidlo)->finalPrice . '&thinsp;Kč';
                }
                $t->assign('cena', $jidlo !== null
                    ? ($cenik->cena($jidlo)->finalPrice . '&thinsp;Kč')
                    : $vec);
                $t->parse('jidlo.druh');
            }
            // hlavička
            foreach (array_keys($dny) as $den) {
                $t->assign('den', mb_ucfirst(self::denNazev($den)));
                $t->parse('jidlo.den');
            }
            // info o pozastaveni
            if ($prodejJidlaUkoncen
                || !$dny
                || $this->jsouVsechnaJidlaPozastavena((array)$jidla)
            ) {
                $t->parse('jidlo.objednavkyZmrazeny');
            }
        } else {
            $t->parse('jidlo.potize');
        }
        $t->assign('pnJidloZmen', self::PN_JIDLO_ZMEN);
        $t->parse('jidlo');

        return $t->text('jidlo');
    }

    private function jsouVsechnaJidlaPozastavena(array $jidla): bool
    {
        foreach ($jidla as $jidlaVJednomDni) {
            foreach ($jidlaVJednomDni as $jidlo) {
                if ($jidlo['stav'] != self::STAV_POZASTAVENY) {
                    return false;
                }
            }
        }

        return true;
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

    public function koupilNejakyPredmet(): bool
    {
        foreach ($this->predmety as $predmet) {
            if ($predmet['kusu_uzivatele'] > 0) {
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

    public function trickaObjednatelnaDoHtml(): string
    {
        return $this->systemoveNastaveni->prodejTricekDo()->format('j. n.');
    }

    public function predmetyBezTricekObjednatelneDoHtml(): string
    {
        return $this->systemoveNastaveni->prodejPredmetuBezTricekDo()->format('j. n.');
    }

    /**
     * Vrátí html kód formuláře s předměty a tričky (bez form značek kvůli
     * integraci více věcí naráz).
     * @todo vyprodání věcí
     */
    public function predmetyHtml()
    {
        $t = new XTemplate(__DIR__ . '/templates/shop-predmety.xtpl');

        // PŘEDMĚTY
        $predmetyZamceny = false;
        if ($this->systemoveNastaveni->prodejPredmetuBezTricekUkoncen()
            || !$this->predmety
            || $this->jsouVsechnyPredmetyNeboTrickaPozastaveny($this->predmety)) {
            $t->parse('predmety.predmetyPozastaveny');
            $predmetyZamceny = true;
        }

        $cenik = $this->cenik();
        foreach ($this->predmety as $predmet) {
            $cena = (float)$predmet['cena_aktualni'];
            $cenaPoSleve = $cena;
            if (Predmet::jeToKostka($predmet[Sql::KOD_PREDMETU])) {
                $cenaPoSleve = (float)$cenik->cenaKostky($predmet);
            } elseif (Predmet::jeToPlacka($predmet[Sql::KOD_PREDMETU])) {
                $cenaPoSleve = (float)$cenik->cenaPlacky($predmet);
            }
            $cena = round($cena);
            $cenaPoSleve = round($cenaPoSleve);
            $menaText = '&thinsp;Kč';
            $cenaText = ($cenaPoSleve !== $cena
                    ? "$cenaPoSleve$menaText/"
                    : '') . $cena . $menaText;
            $t->assign([
                'nazev'          => $predmet['nazev'],
                'cena'           => $cenaText,
                'kusu_uzivatele' => $predmet['kusu_uzivatele'],
                'postName'       => $this->klicP . '[' . $predmet['id_predmetu'] . ']',
            ]);

            if ($predmet['nabizet'] && !$predmetyZamceny) {
                $t->parse('predmety.predmet.nakup');
                $t->parse('predmety.predmet');
            } elseif ($predmet['kusu_uzivatele']) {
                $t->parse('predmety.predmet.fixniPocet');
                $t->parse('predmety.predmet');
            }
            // else přeskočit
        }

        // TRIČKA
        $trickaZamcena = false;
        if ($this->systemoveNastaveni->prodejTricekUkoncen()
            || !$this->tricka
            || $this->jsouVsechnyPredmetyNeboTrickaPozastaveny($this->tricka)
        ) {
            $t->parse('predmety.trickaPozastavena');
            $trickaZamcena = true;
        }

        $koupenaTricka = [];
        foreach ($this->tricka as $tricko) {
            for ($i = 0; $i < $tricko['kusu_uzivatele']; $i++) {
                $koupenaTricka[] = $tricko['id_predmetu'];
            }
        }

        $selecty = $koupenaTricka;
        $selecty[] = 0;

        foreach ($selecty as $i => $idPredmetu) {
            $t->assign([
                'postName' => $this->klicT . '[' . $i . ']',
                'cena'     => round((float)$this->cenaTricka()) . '&thinsp;Kč',
                'rok'      => ROCNIK,
            ]);

            // nagenerovat výběr triček
            if (!$trickaZamcena || $idPredmetu == 0) {
                $t->assign([
                    'id_predmetu' => 0,
                    'nazev'       => '(žádné tričko)',
                ]);
                $t->parse('predmety.tricko.moznost');
            }

            foreach ($this->tricka as $tricko) {
                $koupene = ($tricko['id_predmetu'] == $idPredmetu);
                $nabizet = $tricko['nabizet'];

                if (($trickaZamcena || !$nabizet) && !$koupene) {
                    continue;
                }

                $t->assign([
                    'id_predmetu' => $tricko['id_predmetu'],
                    'nazev'       => ($trickaZamcena
                            ? '&#128274;'
                            : '') . $tricko['nazev'],
                    'selected'    => $koupene
                        ? 'selected'
                        : '',
                ]);
                $t->parse('predmety.tricko.moznost');
            }

            $t->parse('predmety.tricko');
        }

        $t->assign('shopTrickaJs', URL_WEBU . '/soubory/blackarrow/shop/shop-tricka.js?v=1.0');

        $t->parse('predmety');

        return $t->text('predmety');
    }

    private function jsouVsechnyPredmetyNeboTrickaPozastaveny(array $tricka): bool
    {
        foreach ($tricka as $tricko) {
            if ($tricko['stav'] != self::STAV_POZASTAVENY) {
                return false;
            }
        }

        return true;
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

    /** Vrátí html kód s rádiobuttonky pro vyklikání ubytování */
    public function ubytovaniHtml(
        bool $muzeEditovatUkoncenyProdej = false,
        bool $muzeUbytovatPresKapacitu = false,
    ) {
        return $this->ubytovani->ubytovaniHtml(
            muzeEditovatUkoncenyProdej: $muzeEditovatUkoncenyProdej,
            muzeUbytovatPresKapacitu: $muzeUbytovatPresKapacitu,
        );
    }

    public function ubytovaniObjednatelneDoHtml(): string
    {
        return $this->systemoveNastaveni->prodejUbytovaniDo()->format('j. n.');
    }

    /** Vrátí html formuláře se vstupným */
    public function vstupneHtml()
    {
        $t = new XTemplate(__DIR__ . '/templates/shop-vstupne.xtpl');
        $t->assign([
            'jsSlider'              => URL_WEBU . '/soubory/blackarrow/shop/shop-vstupne.js?version='
                                       . md5_file(WWW . '/soubory/blackarrow/shop/shop-vstupne.js'),
            'stav'                  => $this->zakaznik->gcPrihlasen()
                ? $this->vstupne['sum_cena_nakupni'] + $this->vstupnePozde['sum_cena_nakupni']
                : VYCHOZI_DOBROVOLNE_VSTUPNE, // výchozí hodnota
            'postname'              => $this->klicV,
            'min'                   => 0,
            'lonskyPrumerVstupneho' => $this->lonskyPrumerVstupneho(),
            'lonskyRok'             => $this->systemoveNastaveni->rocnik() - 1,
            'vstupneGamaKorekce'    => self::VSTUPNE_GAMA_KOREKCE,
            'smajliky'              => json_encode([
                [1000, URL_WEBU . '/soubory/blackarrow/shop/vstupne-smajliky/6.png'],
                [600, URL_WEBU . '/soubory/blackarrow/shop/vstupne-smajliky/5.png'],
                [250, URL_WEBU . '/soubory/blackarrow/shop/vstupne-smajliky/4.png'],
                [60, URL_WEBU . '/soubory/blackarrow/shop/vstupne-smajliky/3.png'],
                [1, URL_WEBU . '/soubory/blackarrow/shop/vstupne-smajliky/2.png'],
                [0, URL_WEBU . '/soubory/blackarrow/shop/vstupne-smajliky/1.png'],
            ]),
        ]);
        $t->parse('vstupne');

        return $t->text('vstupne');
    }

    private function lonskyPrumerVstupneho(): int
    {
        $pomer = $this->systemoveNastaveni->prumerneLonskeVstupne() / 1000;
        /**
         * https://cs.wikipedia.org/wiki/Gama_korekce
         * @see web/soubory/blackarrow/shop/shop-vstupne.js
         */
        $pomerSGamaKorekci = $pomer ** self::VSTUPNE_GAMA_KOREKCE;
        $procentaSGamaKorekci = $pomerSGamaKorekci * 100;

        return (int)round($procentaSGamaKorekci);
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

    /**
     * Zpracuje část formuláře s předměty a tričky
     * Čáry máry s ručním počítáním diference (místo smazání a náhrady) jsou nut-
     * né kvůli zachování původní nákupní ceny (aktuální cena se totiž mohla od
     * nákupu změnit).
     */
    public function zpracujPredmety()
    {
        if (isset($_POST[$this->klicP]) && isset($_POST[$this->klicT])) {
            // pole s předměty, které jsou vyplněné ve formuláři
            $nove = [];
            foreach ($_POST[$this->klicP] as $idPredmetu => $pocet) {
                for ($i = 0; $i < $pocet; $i++) {
                    $nove[] = (int)$idPredmetu;
                }
            }
            foreach ($_POST[$this->klicT] as $idTricka) { // připojení triček
                if ($idTricka) { // odstranění výběrů „žádné tričko“
                    $nove[] = (int)$idTricka;
                }
            }
            sort($nove);
            // pole s předměty, které už má objednané dříve (bez ubytování)
            $stare = [];
            $o = dbQuery('SELECT id_predmetu FROM shop_nakupy JOIN shop_predmety USING(id_predmetu) WHERE id_uzivatele=' . $this->zakaznik->id() . ' AND rok=' . ROCNIK . ' AND typ IN(' . self::PREDMET . ',' . self::TRICKO . ') ORDER BY id_predmetu');
            while ($r = mysqli_fetch_assoc($o)) {
                $stare[] = (int)$r['id_predmetu'];
            }
            // určení rozdílů polí (note: array_diff ignoruje vícenásobné výskyty hodnot a nedá se použít)
            $i = $j = 0;
            $odstranit = []; //čísla (kvůli nutností více delete dotazů s limitem)
            $pridat = ''; //část sql dotazu
            while (!empty($nove[$i]) || !empty($stare[$j])) {
                if (empty($stare[$j]) || (!empty($nove[$i]) && $nove[$i] < $stare[$j]))
                    // tento prvek není v staré objednávce
                    // zapíšeme si ho pro přidání a přeskočíme na další
                    $pridat .= "\n" . '(' . $this->zakaznik->id() . ',' . $this->objednatel->id() . ',' . $nove[$i] . ',' . ROCNIK . ',(SELECT cena_aktualni FROM shop_predmety WHERE id_predmetu=' . $nove[$i++] . '),NOW()),'; //$i se inkrementuje se po provedení druhého!
                elseif (empty($nove[$i]) || $stare[$j] < $nove[$i])
                    // tento prvek ze staré objednávky není v nové objednávce
                    // zapíšeme si ho, že má být odstraněn, a skočíme na další
                    $odstranit[] = $stare[$j++];
                else
                    // prvky jsou shodné, skočíme o jedna v obou seznamech a neděláme nic
                    $i++ == $j++;
            } //porovnání bez efektu
            // odstranění předmětů, které z objednávky oproti DB zmizely
            foreach ($odstranit as $idPredmetuProOdstraneni) {
                $this->zrusNakupPredmetu($idPredmetuProOdstraneni, 1 /* jen jeden, necheme zlikvidovat všechny ojednávky toho předmětu */);
            }
            // přidání předmětů, které doposud objednané nemá
            $q = 'INSERT INTO shop_nakupy(id_uzivatele,id_objednatele,id_predmetu,rok,cena_nakupni,datum) VALUES ' . $pridat;
            if (substr($q, -1) != ' ') {    // hack testující, jestli se přidala nějaká část
                dbQuery(substr($q, 0, -1)); // odstranění nadbytečné čárky z poslední přidávané části a spuštění dotazu
            }
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

    /**
     * Zpracuje část formuláře s ubytováním
     * @return bool jestli došlo k zpracování dat
     */
    public function zpracujUbytovani(
        bool $vcetneSpolubydliciho = true,
        bool $hlidatKapacituUbytovani = true,
    ): bool {
        return $this->ubytovani->zpracuj($vcetneSpolubydliciho, $hlidatKapacituUbytovani);
    }

    /**
     * Zpracuje část formuláře s vstupným
     */
    public function zpracujVstupne()
    {
        $castka = post($this->klicV);
        if ($castka === null) {
            return;
        }
        // rušíme rozdělení zadané částky na "včas" a "pozdě", vše bude včas
        $vstupneVcas = $castka;
        $vstupnePozde = 0;
        // funkce pro provedení změn
        $zmeny = function (
            $radek,
            $cena,
        ) {
            if ($radek['kusu_uzivatele'] == 0) {
                dbInsert('shop_nakupy', [
                    'cena_nakupni'   => $cena,
                    'id_uzivatele'   => $this->zakaznik->id(),
                    'id_objednatele' => $this->objednatel->id(),
                    'id_predmetu'    => $radek['id_predmetu'],
                    'rok'            => ROCNIK,
                ]);
            } else {
                dbUpdate('shop_nakupy', [
                    'cena_nakupni' => $cena,
                ], [
                    'id_uzivatele' => $this->zakaznik->id(),
                    'id_predmetu'  => $radek['id_predmetu'],
                    'rok'          => ROCNIK,
                ]);
            }
        };
        // zpracování změn
        if ($vstupneVcas != $this->vstupne['sum_cena_nakupni']) {
            $zmeny($this->vstupne, $vstupneVcas);
        }
        if ($vstupnePozde != $this->vstupnePozde['sum_cena_nakupni']) {
            $zmeny($this->vstupnePozde, $vstupnePozde);
        }
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
        $this->zmenObjednavku($ma, $chce);
    }

    private function cenaTricka(): ?float
    {
        $ceny = array_column($this->tricka, 'cena_aktualni');

        return $ceny
            ? (float)max($ceny)
            : null;
    }

    /**
     * @return float Hodnota prevedeneho bonusu prevedena na penize
     * @throws \DbException
     */
    public function kupPrevodBonusuNaPenize(): float
    {
        $nevyuzityBonusZaAktivity = $this->zakaznik->finance()->nevyuzityBonusZaAktivity();
        if (!$nevyuzityBonusZaAktivity) {
            return 0.0;
        }
        $idPredmetuPrevodBonsuNaPenize = dbOneCol(<<<SQL
SELECT id_predmetu
FROM shop_predmety
WHERE typ = $1
ORDER BY model_rok DESC
LIMIT 1
SQL
            , [self::PROPLACENI_BONUSU],
        );
        if (!$idPredmetuPrevodBonsuNaPenize) {
            throw new \RuntimeException(sprintf('Chybi virtualni "predmet" pro prevod bonusu na penize s typem %d', self::PROPLACENI_BONUSU));
        }
        dbQuery(<<<SQL
INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni, datum)
    VALUES ($1, $2, $3, $4, NOW())
SQL
            , [$this->zakaznik->id(), $idPredmetuPrevodBonsuNaPenize, ROCNIK, $nevyuzityBonusZaAktivity],
        );

        return $nevyuzityBonusZaAktivity;
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
            JOIN shop_predmety AS predmety ON nakupy.id_predmetu = predmety.id_predmetu
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
            JOIN shop_predmety AS predmety ON nakupy.id_predmetu = predmety.id_predmetu
            WHERE nakupy.rok = {$this->systemoveNastaveni->rocnik()}
              AND nakupy.id_uzivatele = {$this->zakaznik->id()}
              AND predmety.typ = {$typPredetu}
            SQL,
        );

        return dbAffectedOrNumRows($deleteResult);
    }

    public function zrusVsechnyLetosniObjedavky(string $zdrojZruseni): int
    {
        dbQuery(<<<SQL
            INSERT INTO shop_nakupy_zrusene(id_nakupu, id_uzivatele, id_predmetu, rocnik, cena_nakupni, datum_nakupu, datum_zruseni, zdroj_zruseni)
            SELECT id_nakupu, id_uzivatele, id_predmetu, rok, cena_nakupni, datum, $0, $1
            FROM shop_nakupy
            WHERE shop_nakupy.rok = {$this->systemoveNastaveni->rocnik()} AND shop_nakupy.id_uzivatele = {$this->zakaznik->id()}
            SQL,
            [0 => $this->systemoveNastaveni->ted()->format(DateTimeCz::FORMAT_DB), $zdrojZruseni],
        );
        $result = dbQuery(<<<SQL
            DELETE FROM shop_nakupy
            WHERE rok = {$this->systemoveNastaveni->rocnik()} AND id_uzivatele = {$this->zakaznik->id()}
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
            $prihlasenyLarp->odhlas($this->zakaznik, $odhlasujici, $zdrojZruseni);
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
            $prihlaseneRpg->odhlas($this->zakaznik, $odhlasujici, $zdrojZruseni);
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
            $prihlasenaAktivita->odhlas($this->zakaznik, $odhlasujici, $zdrojZruseni, 0);
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
        $cenaAktualni = dbOneCol("SELECT cena_aktualni FROM shop_predmety WHERE id_predmetu={$idPredmetu}");

        for ($i = 1; $i <= $kusu; $i++) {
            dbQuery(<<<SQL
INSERT INTO shop_nakupy(id_uzivatele,id_objednatele,id_predmetu,rok,cena_nakupni,datum)
VALUES ({$this->zakaznik->id()},{$this->objednatel->id()},{$idPredmetu},{$this->systemoveNastaveni->rocnik()},{$cenaAktualni},NOW())
SQL,
            );
        }

        if ($this->zakaznik->id() === Uzivatel::SYSTEM) {
            $this->zakaznik->finance()->pripis(((float)$cenaAktualni) * $kusu, $this->objednatel, 'anonymní prodej');
        }

        if (!$vcetneOznamemi) {
            return;
        }

        $nazevPredmetu = dbOneCol(
            <<<SQL
            SELECT nazev FROM shop_predmety
            WHERE id_predmetu = $idPredmetu
            SQL,
        );
        $yu = '';
        if ($kusu >= 5) {
            $yu = 'ů';
        } elseif ($kusu > 1) {
            $yu = 'y';
        }
        oznameni("Prodáno $kusu kus$yu $nazevPredmetu");
    }
}
