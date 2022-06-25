<?php declare(strict_types=1);

namespace Gamecon\Shop;

use Uzivatel;
use Cenik;
use ShopUbytovani;
use XTemplate;

/**
 * Třída starající se o e-shop, nákupy, formy a související
 */
class Shop
{
    // TYPY PŘEDMĚTŮ
    public const PREDMET = TypPredmetu::PREDMET;
    public const UBYTOVANI = TypPredmetu::UBYTOVANI;
    public const TRICKO = TypPredmetu::TRICKO;
    public const JIDLO = TypPredmetu::JIDLO;
    public const VSTUPNE = TypPredmetu::VSTUPNE;
    public const PARCON = TypPredmetu::PARCON;
    public const PROPLACENI_BONUSU = TypPredmetu::PROPLACENI_BONUSU;

    // STAVY PŘEDMĚTŮ
    public const MIMO = 0;
    public const VEREJNY = 1;
    public const PODPULTOVY = 2;
    public const POZASTAVENY = 3;

    public const PN_JIDLO = 'cShopJidlo';          // post proměnná pro jídlo
    public const PN_JIDLO_ZMEN = 'cShopJidloZmen'; // post proměnná indikující, že se má jídlo aktualizovat

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
    public static function zrusObjednavkyPro(array $uzivatele, $typ) {
        $povoleneTypy = [self::PREDMET, self::UBYTOVANI, self::TRICKO, self::JIDLO];
        if (!in_array($typ, $povoleneTypy)) {
            throw new \Exception('Tento typ objednávek není možné hromadně zrušit');
        }

        $ids = array_map(static function ($u) {
            return $u->id();
        }, $uzivatele);

        dbQuery(<<<SQL
DELETE sn
FROM shop_nakupy sn
JOIN shop_predmety sp ON sp.id_predmetu = sn.id_predmetu AND sp.typ = $1
WHERE sn.id_uzivatele IN ($2) AND sn.rok = $3
SQL,
            [$typ, $ids, ROK]
        );
    }

    /** Smaže z názvu identifikaci dne */
    public static function bezDne(string $nazev): string {
        $re = ' ?pondělí| ?úterý| ?středa| ?čtvrtek| ?pátek| ?sobota| ?neděle';
        return preg_replace('@' . $re . '@', '', $nazev);
    }

    /**
     * @param int $rok
     * @return Polozka[]
     * @throws \DbException
     */
    public static function letosniPolozky(int $rok = ROK): array {
        $polozkyData = dbFetchAll(<<<SQL
SELECT predmety.id_predmetu,
       TRIM(predmety.nazev) AS nazev,
       predmety.cena_aktualni,
       SUM(nakupy.cena_nakupni) AS suma,
       predmety.model_rok,
       MAX(nakupy.datum) AS naposledy_koupeno_kdy,
       COUNT(nakupy.id_predmetu) AS prodano_kusu,
       predmety.kusu_vyrobeno,
       predmety.typ
FROM shop_predmety AS predmety
JOIN shop_nakupy AS nakupy
    ON predmety.id_predmetu = nakupy.id_predmetu
WHERE model_rok = $0
GROUP BY predmety.id_predmetu, predmety.typ, predmety.ubytovani_den, predmety.nazev
ORDER BY predmety.typ, predmety.ubytovani_den, TRIM(predmety.nazev)
SQL,
            [$rok]
        );
        $polozky = [];
        foreach ($polozkyData as $polozkaData) {
            $polozky[] = new Polozka($polozkaData);
        }
        return $polozky;
    }

    /** @var Uzivatel */
    private $u;
    private $cenik;                     // instance ceníku
    private $nastaveni = [              // případné spec. chování shopu
        'ubytovaniBezZamku' => false,   // ignorovat pozastavení objednávek u ubytování
        'jidloBezZamku' => false,       // ignorovat pozastavení objednávek u jídla
    ];
    private $ubytovani = [];
    private $tricka = [];
    private $predmety = [];
    private $jidlo = [];
    private $ubytovaniOd;
    private $ubytovaniDo;
    private $ubytovaniTypy = [];
    private $vstupne;                   // dobrovolné vstupné (složka zaplacená regurélně včas)
    private $vstupnePozde;              // dobrovolné vstupné (složka zaplacená pozdě)
    private $vstupneJeVcas;             // jestli se dobrovolné vstupné v tento okamžik chápe jako zaplacené včas
    private $klicU = 'shopU';           // klíč formu pro identifikaci polí
    private $klicUPokoj = 'shopUPokoj'; // s kým chce být na pokoji
    private $klicV = 'shopV';           // klíč formu pro identifikaci vstupného
    private $klicP = 'shopP';           // klíč formu pro identifikaci polí
    private $klicT = 'shopT';           // klíč formu pro identifikaci polí s tričkama
    private $klicS = 'shopS';           // klíč formu pro identifikaci polí se slevami

    public function __construct(Uzivatel $u, array $nastaveni = null) {
        $this->u = $u;
        $this->cenik = new Cenik($u, $u->finance()->bonusZaVedeniAktivit());
        if (is_array($nastaveni)) {
            $this->nastaveni = array_replace($this->nastaveni, $nastaveni);
        }

        // vybrat všechny předměty pro tento rok + předměty v nabídce + předměty, které si koupil
        $o = dbQuery(<<<SQL
SELECT *
FROM (
      SELECT
        p.id_predmetu, p.model_rok, p.cena_aktualni, p.stav, p.auto, p.nabizet_do, p.kusu_vyrobeno, p.typ, p.ubytovani_den, p.popis,
        IF(p.model_rok = $1, nazev, CONCAT(nazev, ' (', popis, ')')) AS nazev,
        COUNT(IF(n.rok = $1, 1, NULL)) kusu_prodano,
        COUNT(IF(n.id_uzivatele = $2 AND n.rok = $1, 1, NULL)) kusu_uzivatele,
        SUM(IF(n.id_uzivatele = $2 AND n.rok = $1, cena_nakupni, 0)) sum_cena_nakupni
      FROM shop_predmety p
      LEFT JOIN shop_nakupy n USING(id_predmetu)
      WHERE stav > $0 OR n.rok = $1
      GROUP BY id_predmetu
) AS seskupeno
ORDER BY typ, ubytovani_den, nazev, model_rok DESC, id_predmetu ASC
SQL
            , [self::MIMO, ROK, $this->u->id()]);

        //inicializace
        $this->jidlo['dny'] = [];
        $this->jidlo['druhy'] = [];

        while ($r = mysqli_fetch_assoc($o)) {
            $typ = $r['typ'];
            if ($typ == self::PROPLACENI_BONUSU) {
                continue; // není určeno k přímému prodeji
            }
            unset($fronta); // $fronta reference na frontu kam vložit předmět (nelze dát =null, přepsalo by předchozí vrch fronty)
            if ($r['nabizet_do'] && strtotime($r['nabizet_do']) < time()) {
                $r['stav'] = self::POZASTAVENY;
            }
            $r['nabizet'] = $r['stav'] == self::VEREJNY; // v základu nabízet vše v stavu 1
            // rozlišení kam ukládat a jestli nabízet podle typu
            if ($typ == self::PREDMET) {
                $fronta = &$this->predmety[];
            } elseif ($typ == self::JIDLO) {
                $den = $r['ubytovani_den'];
                $druh = self::bezDne($r['nazev']);
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
                $r['nabizet'] = $r['nabizet'] || ($r['stav'] == self::POZASTAVENY && $this->nastaveni['jidloBezZamku']);
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
                $r['nabizet'] = $r['nabizet'] || ($r['stav'] == self::POZASTAVENY && $this->nastaveni['ubytovaniBezZamku']);
                $fronta = &$this->ubytovani[];
            } elseif ($typ == self::TRICKO) {
                $smiModre = $this->u->maPravo(P_TRICKO_MODRA_BARVA);
                $smiCervene = $this->u->maPravo(P_TRICKO_CERVENA_BARVA);
                $r['nabizet'] = (
                    $r['nabizet']
                    || ($r['stav'] == self::PODPULTOVY && mb_stripos($r['nazev'], 'modré') !== false && $smiModre)
                    || ($r['stav'] == self::PODPULTOVY && mb_stripos($r['nazev'], 'červené') !== false && $smiCervene)
                );
                $fronta = &$this->tricka[];
                if (AUTOMATICKY_VYBER_TRICKA) {
                    // hack pro výběr správného automaticky objednaného trička
                    if ($smiCervene) {
                        $barva = 'červené';
                    } elseif ($smiModre) {
                        $barva = 'modré';
                    } else {
                        $barva = '.*';
                    }
                    if ($this->u->pohlavi() == 'f') {
                        $typTricka = 'tílko.*dámské S';
                    } else {
                        $typTricka = 'tričko.*pánské L';
                    }
                    $r['auto'] = (
                        $r['nabizet'] &&
                        preg_match("@$barva@i", $r['nazev']) &&
                        preg_match("@$typTricka@i", $r['nazev'])
                    );
                }
            } elseif ($typ == self::VSTUPNE) {
                if (strpos($r['nazev'], 'pozdě') === false) {
                    $this->vstupne = $r;
                    $this->vstupneJeVcas = $r['stav'] == self::PODPULTOVY;
                } else {
                    $this->vstupnePozde = $r;
                }
            } else {
                throw new \Exception('Objevil se nepodporovaný typ předmětu s č.' . var_export($r['typ'], true));
            }
            // vybrané předměty nastavit jako automaticky objednané
            if ($r['nabizet'] && $r['auto'] && $this->prvniNakup()) {
                $r['kusu_uzivatele']++;
            }
            // finální uložení předmětu na vrchol dané fronty
            $fronta = $r;
        }

        $this->ubytovani = new ShopUbytovani($this->ubytovani, $this->u); // náhrada reprezentace polem za objekt
    }

    private static function denNazev($cislo) {
        return self::$dny[$cislo];
    }

    /**
     * Vrátí html kód formuláře s výběrem jídla
     */
    public function jidloHtml() {
        // inicializace
        ksort($this->jidlo['druhy']);
        $dny = $this->jidlo['dny'];
        $druhy = $this->jidlo['druhy'];
        $jidla = $this->jidlo['jidla'];
        // vykreslení
        $t = new XTemplate(__DIR__ . '/shop-jidlo.xtpl');
        if (!defined('PRODEJ_JIDLA_POZASTAVEN') || !PRODEJ_JIDLA_POZASTAVEN) {
            foreach (array_keys($druhy) as $druh) {
                foreach (array_keys($dny) as $den) {
                    $jidlo = $jidla[$den][$druh] ?? null;
                    if ($jidlo && ($jidlo['nabizet'] || $jidlo['kusu_uzivatele'])) {
                        $t->assign('selected', $jidlo['kusu_uzivatele'] > 0 ? 'checked' : '');
                        $t->assign('pnName', self::PN_JIDLO . '[' . $jidlo['id_predmetu'] . ']');
                        $t->parse($jidlo['stav'] == self::POZASTAVENY && !$this->nastaveni['jidloBezZamku']
                            ? 'jidlo.druh.den.locked'
                            : 'jidlo.druh.den.checkbox'
                        );
                    }
                    $t->parse('jidlo.druh.den');
                }
                $t->assign('druh', $druh);
                $t->assign('cena', $this->cenik->shop($jidlo) . '&thinsp;Kč');
                $t->parse('jidlo.druh');
            }
            // hlavička
            foreach (array_keys($dny) as $den) {
                $t->assign('den', mb_ucfirst(self::denNazev($den)));
                $t->parse('jidlo.den');
            }
            // info o pozastaveni
            if (!$dny || $this->jsouVsechnaJidlaPozastavena((array)$jidla)) {
                $t->parse('jidlo.objednavkyZmrazeny');
            }
        } else {
            $t->parse('jidlo.potize');
        }
        $t->assign('pnJidloZmen', self::PN_JIDLO_ZMEN);
        $t->parse('jidlo');
        return $t->text('jidlo');
    }

    private function jsouVsechnaJidlaPozastavena(array $jidla): bool {
        foreach ($jidla as $jidlaVJednomDni) {
            foreach ($jidlaVJednomDni as $jidlo) {
                if ($jidlo['stav'] != self::POZASTAVENY) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Vrátí html kód formuláře s předměty a tričky (bez form značek kvůli
     * integraci více věcí naráz).
     * @todo vyprodání věcí
     */
    public function predmetyHtml() {
        $t = new XTemplate(__DIR__ . '/shop-predmety.xtpl');

        // předměty
        if (current($this->predmety)['stav'] == self::POZASTAVENY) {
            $t->parse('predmety.predmetyPozastaveny');
        }

        foreach ($this->predmety as $predmet) {
            $t->assign([
                'nazev' => $predmet['nazev'],
                'cena' => round($predmet['cena_aktualni']) . '&thinsp;Kč',
                'kusu_uzivatele' => $predmet['kusu_uzivatele'],
                'postName' => $this->klicP . '[' . $predmet['id_predmetu'] . ']',
            ]);

            if ($predmet['nabizet']) {
                $t->parse('predmety.predmet.nakup');
                $t->parse('predmety.predmet');
            } else if ($predmet['kusu_uzivatele']) {
                $t->parse('predmety.predmet.fixniPocet');
                $t->parse('predmety.predmet');
            } else {
                // přeskočit
            }
        }

        // trička
        $zamceno = false;
        if (current($this->tricka)['stav'] == self::POZASTAVENY) {
            $t->parse('predmety.trickaPozastavena');
            $zamceno = true;
        }

        $koupenaTricka = [];
        foreach ($this->tricka as $tricko) {
            for ($i = 0; $i < $tricko['kusu_uzivatele']; $i++) {
                $koupenaTricka[] = $tricko['id_predmetu'];
            }
        }

        $selecty = $koupenaTricka;
        $selecty[] = 0;

        foreach ($selecty as $i => $pid) {
            $t->assign([
                'postName' => $this->klicT . '[' . $i . ']',
                'cena' => round($this->cenaTricka()) . '&thinsp;Kč',
                'rok' => ROK,
            ]);

            // nagenerovat výběr triček
            if (!$zamceno || $pid == 0) {
                $t->assign([
                    'id_predmetu' => 0,
                    'nazev' => '(žádné tričko)',
                ]);
                $t->parse('predmety.tricko.moznost');
            }

            foreach ($this->tricka as $tricko) {
                $koupene = ($tricko['id_predmetu'] == $pid);
                $nabizet = $tricko['nabizet'];

                if (($zamceno || !$nabizet) && !$koupene) {
                    continue;
                }

                $t->assign([
                    'id_predmetu' => $tricko['id_predmetu'],
                    'nazev' => ($zamceno ? '&#128274;' : '') . $tricko['nazev'],
                    'selected' => $koupene ? 'selected' : '',
                ]);
                $t->parse('predmety.tricko.moznost');
            }

            $t->parse('predmety.tricko');
        }

        $t->parse('predmety');
        return $t->text('predmety');
    }

    /**
     * Jestli je toto prvním nákupem daného uživatele
     */
    private function prvniNakup() {
        return !$this->u->gcPrihlasen();
    }

    /** Vrátí html kód s rádiobuttonky pro vyklikání ubytování */
    public function ubytovaniHtml() {
        return $this->ubytovani->html();
    }

    public function covidFreePotrvzeniHtml(int $rok): string {
        return $this->u->covidFreePotvrzeniHtml($rok);
    }

    /** Vrátí html formuláře se vstupným */
    public function vstupneHtml() {
        $t = new XTemplate(__DIR__ . '/shop-vstupne.xtpl');
        $t->assign([
            'jsSlider' => URL_WEBU . '/soubory/blackarrow/shop/shop-vstupne.js',
            'stav' => $this->u->gcPrihlasen()
                ? $this->vstupne['sum_cena_nakupni'] + $this->vstupnePozde['sum_cena_nakupni']
                : VYCHOZI_DOBROVOLNE_VSTUPNE, // výchozí hodnota
            'postname' => $this->klicV,
            'min' => $this->vstupneJeVcas ? 0 : $this->vstupne['sum_cena_nakupni'],
            'smajliky' => json_encode([
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

    /**
     * Upraví objednávku z pole id $stare na pole $nove
     * @todo zaintegrovat i jinde (ale zároveň nutno zobecnit pro vícenásobné
     * nákupy jednoho ID)
     */
    private function zmenObjednavku($stare, $nove) {
        $nechce = array_diff($stare, $nove);
        $chceNove = array_diff($nove, $stare);
        // přírustky
        $values = '';
        foreach ($chceNove as $n) {
            $sel = 'SELECT cena_aktualni FROM shop_predmety WHERE id_predmetu = ' . $n;
            $values .= "\n" . '(' . $this->u->id() . ',' . $n . ',' . ROK . ',(' . $sel . '),NOW()),';
        }
        if ($values) {
            $values[strlen($values) - 1] = ';';
            dbQuery('INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni, datum) VALUES ' . $values);
        }
        // mazání
        if ($nechce) {
            dbQueryS('DELETE FROM shop_nakupy WHERE id_uzivatele = $1 AND rok = $2 AND id_predmetu IN($3)', [
                $this->u->id(), ROK, $nechce,
            ]);
        }
    }

    /**
     * Zpracuje část formuláře s předměty a tričky
     * Čáry máry s ručním počítáním diference (místo smazání a náhrady) jsou nut-
     * né kvůli zachování původní nákupní ceny (aktuální cena se totiž mohla od
     * nákupu změnit).
     */
    public function zpracujPredmety() {
        if (isset($_POST[$this->klicP]) && isset($_POST[$this->klicT])) {
            // pole s předměty, které jsou vyplněné ve formuláři
            $nove = [];
            foreach ($_POST[$this->klicP] as $idPredmetu => $pocet)
                for ($i = 0; $i < $pocet; $i++)
                    $nove[] = (int)$idPredmetu;
            foreach ($_POST[$this->klicT] as $idTricka) // připojení triček
                if ($idTricka) // odstranění výběrů „žádné tričko“
                    $nove[] = (int)$idTricka;
            sort($nove);
            // pole s předměty, které už má objednané dříve (bez ubytování)
            $stare = [];
            $o = dbQuery('SELECT id_predmetu FROM shop_nakupy JOIN shop_predmety USING(id_predmetu) WHERE id_uzivatele=' . $this->u->id() . ' AND rok=' . ROK . ' AND typ IN(' . self::PREDMET . ',' . self::TRICKO . ') ORDER BY id_predmetu');
            while ($r = mysqli_fetch_assoc($o))
                $stare[] = (int)$r['id_predmetu'];
            // určení rozdílů polí (note: array_diff ignoruje vícenásobné výskyty hodnot a nedá se použít)
            $i = $j = 0;
            $odstranit = []; //čísla (kvůli nutností více delete dotazů s limitem)
            $pridat = ''; //část sql dotazu
            while (!empty($nove[$i]) || !empty($stare[$j]))
                if (empty($stare[$j]) || (!empty($nove[$i]) && $nove[$i] < $stare[$j]))
                    // tento prvek není v staré objednávce
                    // zapíšeme si ho pro přidání a přeskočíme na další
                    $pridat .= "\n" . '(' . $this->u->id() . ',' . $nove[$i] . ',' . ROK . ',(SELECT cena_aktualni FROM shop_predmety WHERE id_predmetu=' . $nove[$i++] . '),NOW()),'; //$i se inkrementuje se po provedení druhého!
                else if (empty($nove[$i]) || $stare[$j] < $nove[$i])
                    // tento prvek ze staré objednávky není v nové objednávce
                    // zapíšeme si ho, že má být odstraněn, a skočíme na další
                    $odstranit[] = $stare[$j++];
                else
                    // prvky jsou shodné, skočíme o jedna v obou seznamech a neděláme nic
                    $i++ == $j++; //porovnání bez efektu
            // odstranění předmětů, které z objednávky oproti DB zmizely
            foreach ($odstranit as $idPredmetu)
                dbQuery('DELETE FROM shop_nakupy WHERE id_uzivatele=' . $this->u->id() . ' AND id_predmetu=' . $idPredmetu . ' AND rok=' . ROK . ' LIMIT 1');
            // přidání předmětů, které doposud objednané nemá
            $q = 'INSERT INTO shop_nakupy(id_uzivatele,id_predmetu,rok,cena_nakupni,datum) VALUES ' . $pridat;
            if (substr($q, -1) != ' ') { // hack testující, jestli se přidala nějaká část
                dbQuery(substr($q, 0, -1)); // odstranění nadbytečné čárky z poslední přidávané části a spuštění dotazu
            }
        }
    }

    /**
     * Zpracuje část formuláře s ubytováním
     * @return bool jestli došlo k zpracování dat
     */
    public function zpracujUbytovani() {
        return $this->ubytovani->zpracuj();
    }

    /**
     * Zpracuje část formuláře s vstupným
     */
    public function zpracujVstupne() {
        $castka = post($this->klicV);
        if ($castka === null) {
            return;
        }
        // rozdělení zadané částky na "včas" a "pozdě"
        $vstupneVcas = $this->vstupneJeVcas ? $castka : $this->vstupne['sum_cena_nakupni'];
        $vstupnePozde = $this->vstupneJeVcas ? 0 : max(0, $castka - $this->vstupne['sum_cena_nakupni']);
        // funkce pro provedení změn
        $zmeny = function ($radek, $cena) {
            if ($radek['kusu_uzivatele'] == 0) {
                dbInsert('shop_nakupy', [
                    'cena_nakupni' => $cena,
                    'id_uzivatele' => $this->u->id(),
                    'id_predmetu' => $radek['id_predmetu'],
                    'rok' => ROK,
                ]);
            } else {
                dbUpdate('shop_nakupy', [
                    'cena_nakupni' => $cena,
                ], [
                    'id_uzivatele' => $this->u->id(),
                    'id_predmetu' => $radek['id_predmetu'],
                    'rok' => ROK,
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
    public function zpracujJidlo() {
        if (!isset($_POST[self::PN_JIDLO_ZMEN])) {
            return;
        }
        $ma = array_keys($this->jidlo['jidloObednano'] ?? []);
        $chce = array_keys(post(self::PN_JIDLO) ?: []);
        $this->zmenObjednavku($ma, $chce);
    }

    private function cenaTricka() {
        $ceny = array_column($this->tricka, 'cena_aktualni');
        return max($ceny);
    }

    /**
     * @return float Hodnota prevedeneho bonusu prevedena na penize
     * @throws \DbException
     */
    public function kupPrevodBonusuNaPenize(): float {
        $nevyuzityBonusZaAktivity = $this->u->finance()->nevyuzityBonusZaAktivity();
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
            , [self::PROPLACENI_BONUSU]
        );
        if (!$idPredmetuPrevodBonsuNaPenize) {
            throw new \RuntimeException(sprintf('Chybi virtualni "predmet" pro prevod bonusu na penize s typem %d', self::PROPLACENI_BONUSU));
        }
        dbQuery(<<<SQL
INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni, datum)
    VALUES ($1, $2, $3, $4, NOW())
SQL
            , [$this->u->id(), $idPredmetuPrevodBonsuNaPenize, ROK, $nevyuzityBonusZaAktivity]
        );
        return $nevyuzityBonusZaAktivity;
    }

    public function dejPopisUbytovani(): string {
        return $this->ubytovani->kratkyPopis();
    }
}
