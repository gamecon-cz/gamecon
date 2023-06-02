<?php

declare(strict_types=1);

namespace Gamecon\Report;

use Gamecon\Shop\StavPredmetu;
use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Report;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Role\Role;
use Uzivatel;
use DateInterval;
use Gamecon\Jidlo;

// takzvaný BFGR (Big f**king Gandalf report)
class BfgrReport
{
    // poradi je dulezite, udava prioritu
    private const ID_ROLI_PRO_POZICI = [
        Role::ORGANIZATOR,
        Role::PUL_ORG_BONUS_UBYTKO,
        Role::PUL_ORG_BONUS_TRICKO,
        Role::LETOSNI_VYPRAVEC,
        Role::LETOSNI_PARTNER,
        Role::LETOSNI_DOBROVOLNIK_SENIOR,
    ];

    public function __construct(private readonly SystemoveNastaveni $systemoveNastaveni)
    {
    }

    public function exportuj(
        ?string    $format,
        bool       $vcetneStavuNeplatice = false,
        string     $doSouboru = null,
        string|int $idUzivatele = null,
    )
    {
        $ucastPodleRoku = [];
        $maxRok         = $this->systemoveNastaveni->poRegistraciUcastniku()
            ? $this->systemoveNastaveni->rocnik()
            : $this->systemoveNastaveni->rocnik() - 1;
        for ($rokUcasti = 2009; $rokUcasti <= $maxRok; $rokUcasti++) {
            $ucastPodleRoku[$rokUcasti] = 'účast ' . $rokUcasti;
        }

//        $letosniPlacky = $this->letosniPlacky();

        $letosniKostky = $this->letosniKostky();

        $letosniJidla = $this->letosniJidla();

        $letosniOstatniPredmety = $this->letosniOstatniPredmety();

        $letosniCovidTesty = $this->letosniCovidTesty();

        $rocnik           = $this->systemoveNastaveni->rocnik();
        $predmetUbytovani = TypPredmetu::UBYTOVANI;
        $typUcast         = Role::TYP_UCAST;
        $o                = dbQuery(<<<SQL
SELECT
    uzivatele_hodnoty.*,
    prihlasen.posazen AS prihlasen_na_gc_kdy,
    pritomen.posazen as prosel_infopultem_kdy,
    odjel.posazen as odjel_kdy,
    ( SELECT MIN(shop_predmety.ubytovani_den) FROM shop_nakupy JOIN shop_predmety USING(id_predmetu) WHERE shop_nakupy.rok=$rocnik AND shop_nakupy.id_uzivatele=prihlasen.id_uzivatele AND shop_predmety.typ=$predmetUbytovani ) AS den_prvni,
    ( SELECT MAX(shop_predmety.ubytovani_den) FROM shop_nakupy JOIN shop_predmety USING(id_predmetu) WHERE shop_nakupy.rok=$rocnik AND shop_nakupy.id_uzivatele=prihlasen.id_uzivatele AND shop_predmety.typ=$predmetUbytovani ) AS den_posledni,
    ( SELECT MAX(shop_predmety.nazev) FROM shop_nakupy JOIN shop_predmety USING(id_predmetu) WHERE shop_nakupy.rok=$rocnik AND shop_nakupy.id_uzivatele=prihlasen.id_uzivatele AND shop_predmety.typ=$predmetUbytovani ) AS ubytovani_typ,
    ( SELECT GROUP_CONCAT(r_prava_soupis.jmeno_prava SEPARATOR ', ')
      FROM platne_role_uzivatelu
      JOIN prava_role
          ON platne_role_uzivatelu.id_role = prava_role.id_role
      JOIN r_prava_soupis
          ON prava_role.id_prava = r_prava_soupis.id_prava
      JOIN role_seznam
          ON platne_role_uzivatelu.id_role = role_seznam.id_role
      WHERE platne_role_uzivatelu.id_uzivatele = uzivatele_hodnoty.id_uzivatele
          AND role_seznam.typ_role != '$typUcast'
      GROUP BY platne_role_uzivatelu.id_uzivatele
    ) AS pravaZDotazu,
    ( SELECT GROUP_CONCAT(role_seznam.nazev_role ORDER BY role_seznam.id_role DESC SEPARATOR ', ')
      FROM role_seznam
      JOIN platne_role_uzivatelu
          ON role_seznam.id_role = platne_role_uzivatelu.id_role
      WHERE platne_role_uzivatelu.id_uzivatele = uzivatele_hodnoty.id_uzivatele
          AND role_seznam.typ_role = '$typUcast'
      GROUP BY platne_role_uzivatelu.id_uzivatele
    ) AS ucastZDotazu,
    ( SELECT GROUP_CONCAT(platne_role_uzivatelu.id_role SEPARATOR ',')
      FROM platne_role_uzivatelu
      JOIN role_seznam
          ON platne_role_uzivatelu.id_role = role_seznam.id_role
      WHERE platne_role_uzivatelu.id_uzivatele=uzivatele_hodnoty.id_uzivatele
          AND role_seznam.typ_role != '$typUcast'
      GROUP BY platne_role_uzivatelu.id_uzivatele
    ) AS idckaRoliZDotazu,
    ( SELECT GROUP_CONCAT(role_seznam.nazev_role SEPARATOR ', ')
      FROM platne_role_uzivatelu
      JOIN role_seznam
          ON platne_role_uzivatelu.id_role = role_seznam.id_role
      WHERE platne_role_uzivatelu.id_uzivatele=uzivatele_hodnoty.id_uzivatele
          AND role_seznam.typ_role != '$typUcast'
      GROUP BY platne_role_uzivatelu.id_uzivatele
    ) AS roleZDotazu
FROM uzivatele_hodnoty
LEFT JOIN platne_role_uzivatelu AS prihlasen ON (prihlasen.id_role = $0 AND prihlasen.id_uzivatele = uzivatele_hodnoty.id_uzivatele)
LEFT JOIN platne_role_uzivatelu AS pritomen ON (pritomen.id_role = $1 AND pritomen.id_uzivatele = uzivatele_hodnoty.id_uzivatele)
LEFT JOIN platne_role_uzivatelu AS odjel ON(odjel.id_role = $2 AND odjel.id_uzivatele = uzivatele_hodnoty.id_uzivatele)
WHERE (
    prihlasen.id_uzivatele IS NOT NULL -- left join, takže může být NULL ve smyslu "nemáme záznam" = "není přihlášen"
    OR pritomen.id_uzivatele IS NOT NULL -- tohle by bylo hodně divné, musela by být díra v systému, aby nebyl přihlášen ale byl přítomen, ale radši...
    OR EXISTS(SELECT * FROM shop_nakupy WHERE uzivatele_hodnoty.id_uzivatele = shop_nakupy.id_uzivatele AND shop_nakupy.rok = $rocnik)
    OR EXISTS(SELECT * FROM platby WHERE platby.id_uzivatele = uzivatele_hodnoty.id_uzivatele AND platby.rok = $rocnik)
)
  AND IF ($3 IS NULL, TRUE, uzivatele_hodnoty.id_uzivatele = $3)
SQL,
            [
                0 => Role::PRIHLASEN_NA_LETOSNI_GC,
                1 => Role::PRITOMEN_NA_LETOSNIM_GC,
                2 => Role::ODJEL_Z_LETOSNIHO_GC,
                3 => $idUzivatele ?: null,
            ],
        );
        if (mysqli_num_rows($o) === 0) {
            if ($doSouboru) {
                file_put_contents($doSouboru, '');
                return;
            }
            exit('V tabulce nejsou žádná data.');
        }

//        $letosniPlackyKlice          = array_fill_keys($letosniPlacky, null);
//        $letosniKostkyKlice          = array_fill_keys($letosniKostky, null);
//        $letosniJidlaKlice           = array_fill_keys($letosniJidla, null);
//        $letosniOstatniPredmetyKlice = array_fill_keys($letosniOstatniPredmety, null);
//        $letosniCovidTestyKlice      = array_fill_keys($letosniCovidTesty, null);

        while ($r = mysqli_fetch_assoc($o)) {
            $navstevnik = new Uzivatel($r);
            $navstevnik->nactiPrava(); // sql subdotaz, zlo
            $finance        = $navstevnik->finance();
            $ucastiHistorie = [];
            foreach ($ucastPodleRoku as $rocnik => $nazevUcasti) {
                $ucastiHistorie[$nazevUcasti] = $navstevnik->gcPritomen($rocnik)
                    ? 'ano'
                    : 'ne';
            }
            $stat = '';
            try {
                $stat = $navstevnik->stat();
            } catch (\Throwable $e) {
            }

            $obsah[] = array_merge(
                [
                    'Účastník'            => array_merge(
                        [
                            'ID'                => $r['id_uzivatele'],
                            'Příjmení'          => $r['prijmeni_uzivatele'],
                            'Jméno'             => $r['jmeno_uzivatele'],
                            'Přezdívka'         => $r['login_uzivatele'],
                            'Mail'              => $r['email1_uzivatele'],
                            'Pozice'            => $this->nazevRole(explode(',', (string)$r['idckaRoliZDotazu'])),
                            'Role'              => $r['roleZDotazu'],
                            'Práva'             => nahradNazvyKonstantZaHodnoty((string)$r['pravaZDotazu']),
                            'Účast'             => $r['ucastZDotazu'],
                            'Datum registrace'  => $this->excelDatum($r['prihlasen_na_gc_kdy']),
                            'Prošel infopultem' => $this->excelDatum($r['prosel_infopultem_kdy']),
                            'Odjel kdy'         => $this->excelDatum($r['odjel_kdy']),
                        ],
                        $vcetneStavuNeplatice
                            ? [
                            'Kategorie neplatiče'         => $navstevnik->finance()->kategorieNeplatice()->ciselnaKategoriiNeplatice(),
                            'Bude odhlášen jako neplatič' => $navstevnik->finance()->kategorieNeplatice()->melByBytOdhlasen(),
                        ]
                            : [],
                    ),
                    'Datum narození'      => [
                        'Den'   => date('j', strtotime($r['datum_narozeni'])),
                        'Měsíc' => date('n', strtotime($r['datum_narozeni'])),
                        'Rok'   => date('Y', strtotime($r['datum_narozeni'])),
                    ],
                    'Bydliště'            => [
                        'Stát'  => $stat,
                        'Město' => $r['mesto_uzivatele'],
                        'Ulice' => $r['ulice_a_cp_uzivatele'],
                        'PSČ'   => $r['psc_uzivatele'],
                        'Škola' => $r['skola'],
                    ],
                    'Ubytovací informace' => array_merge(
                        [
                            'Chci bydlet s'          => $r['ubytovan_s'],
                            'První noc'              => $r['den_prvni'] === null
                                ? '-'
                                : (new DateTimeCz(DEN_PRVNI_UBYTOVANI))->add(new DateInterval("P$r[den_prvni]D"))->format('j.n.Y'),
                            'Poslední noc (počátek)' => $r['den_posledni'] === null
                                ? '-'
                                : (new DateTimeCz(DEN_PRVNI_UBYTOVANI))->add(new DateInterval("P$r[den_posledni]D"))->format('j.n.Y'),
                            'Typ'                    => $this->typUbytovani((string)$r['ubytovani_typ']),
                            'Dorazil na GC'          => $navstevnik->gcPritomen() ? 'ano' : 'ne',
                        ],
                        $ucastiHistorie,
                    ),
                ],
                [
                    'Celkové náklady' => [
                        'Celkem dní' => $pobyt = ($r['den_prvni'] !== null
                            ? $r['den_posledni'] - $r['den_prvni'] + 1
                            : 0
                        ),
                        'Cena / den' => $pobyt ? $finance->cenaUbytovani() / $pobyt : 0,
                        'Ubytování'  => $finance->cenaUbytovani(),
                        'Předměty'   => $finance->cenaPredmetu(),
                        'Strava'     => $finance->cenaStravy(),
                    ],
                    'Ostatní platby'  => [
                        'Aktivity'                           => $finance->cenaAktivit(),
                        'Dobrovolné vstupné'                 => $finance->vstupne(),
                        'Dobrovolné vstupné (pozdě)'         => $finance->vstupnePozde(),
                        'Suma slev'                          => $this->excelCislo($finance->slevaObecna()),
                        'Bonus za vedení aktivit'            => $finance->bonusZaVedeniAktivit(),
                        'Využitý bonus za vedení aktivit'    => $finance->vyuzityBonusZaAktivity(),
                        'Proplacený bonus za vedení aktivit' => $finance->proplacenyBonusZaAktivity(),
                        'Brigádnické odměny'                 => $finance->brigadnickaOdmena(),
                        'Stav'                               => $this->excelCislo($finance->stav()),
                        'Zůstatek z minula'                  => $this->excelCislo($r['zustatek']),
                        'Připsané platby'                    => $this->excelCislo($finance->sumaPlateb()),
                        'První blok'                         => $this->excelDatum($navstevnik->prvniBlok()),
                        'Poslední blok'                      => $this->excelDatum($navstevnik->posledniBlok()),
                        'Dobrovolník pozice'                 => $r['pomoc_typ'],
                        'Dobrovolník info'                   => $r['pomoc_vice'],
                        'Storno aktivit'                     => $finance->sumaStorna(),
                        'Dárky a zlevněné nákupy'            => implode(", ", array_merge($finance->slevyVse(), $finance->slevyAktivity())),
                        'Objednávky'                         => strip_tags($finance->prehledPopis()),
                        'Poznámka'                           => strip_tags((string)$r['poznamka']),
                    ],
                    'Eshop'           => array_merge(
                        [
                            'Průměrná sleva na aktivity %' => $finance->slevaZaAktivityVProcentech(),
                        ],
                        $this->dejNazvyAPoctyPlacek($navstevnik),
                        $this->dejNazvyAPoctyKostek($navstevnik, $letosniKostky),
                        $this->dejNazvyAPoctyJidel($navstevnik, $letosniJidla),
                        $this->dejNazvyAPoctySvrsku($navstevnik),
//                        $this->dejNazvyAPoctyTasek($navstevnik),
                        $this->dejNazvyAPoctyOstatnichPredmetu($navstevnik, $letosniOstatniPredmety),
                        // $this->letosniOstatniPredmetyPocty($r, $letosniOstatniPredmetyKlice),
                        $this->dejNazvyAPoctyCovidTestu($navstevnik, $letosniCovidTesty), // "dát pls až nakonec", tak pravil Gandalf 30. 7. 2021
                    ),
                ],
            );
        }

        $indexySloupcuSBydlistem = Report::dejIndexyKlicuPodsloupcuDruhehoRadkuDleKliceVPrvnimRadku('Bydliště', $obsah);
        $sirkaSloupcuSBydlistem  = array_fill_keys($indexySloupcuSBydlistem, 30);

        $indexySloupcuSDatemNarozeni = Report::dejIndexyKlicuPodsloupcuDruhehoRadkuDleKliceVPrvnimRadku('Datum narození', $obsah);
        $sirkaSloupcuSDatemNarozeni  = array_fill_keys($indexySloupcuSDatemNarozeni, 10);

        $indexSloupceSPravy = Report::dejIndexKlicePodsloupceDruhehoRadku('Práva', $obsah);
        $sirkaSloupcuSPravy = [$indexSloupceSPravy => 50];

        $konfiguraceReportu = (new KonfiguraceReportu())
            ->setRowToFreeze(KonfiguraceReportu::NO_ROW_TO_FREEZE)
            ->setMaxGenericColumnWidth(50)
            ->setColumnsWidths($sirkaSloupcuSBydlistem + $sirkaSloupcuSDatemNarozeni + $sirkaSloupcuSPravy);

        if ($doSouboru) {
            $konfiguraceReportu->setDestinationFile($doSouboru);
        }

        Report::zPoleSDvojitouHlavickou($obsah, Report::HLAVICKU_ZACINAT_VElKYM_PISMENEM)
            ->tFormat($format, null, $konfiguraceReportu);
    }

    private function letosniOstatniPredmetyPocty(array $zaznam, array $letosniOstatniPredmetyKlice): array
    {
        return array_intersect_key($zaznam, $letosniOstatniPredmetyKlice);
    }

    private function excelDatum(?string $datum)
    {
        if (!$datum) {
            return null;
        }
        return date('j.n.Y G:i', strtotime($datum));
    }

    private function excelCislo(string|int|float $cislo): string
    {
        $excelCislo = str_replace('.', ',', (string)$cislo);
        if ((float)(int)$excelCislo === (float)$excelCislo) {
            $excelCislo = (string)(int)$excelCislo; // odstraníme .00
        }
        return $excelCislo;
    }

    private function typUbytovani(string $typ)
    { // ubytování typ - z názvu předmětu odhadne typ
        return preg_replace('@ ?(pondělí|úterý|středa|čtvrtek|pátek|sobota|neděle) ?@iu', '', $typ);
    }

    private function nazevRole(array $idckaRoli): string
    {
        foreach ($this->jmenaRoliProPozici() as $idRole => $jmenoRole) {
            if (in_array($idRole, $idckaRoli, false)) {
                return $jmenoRole;
            }
        }
        return 'Účastník';
    }

    private function jmenaRoliProPozici(): array
    {
        static $jmenaRoliProPozici = [];
        if (!$jmenaRoliProPozici) {
            foreach (self::ID_ROLI_PRO_POZICI as $idRole) {
                $jmenaRoliProPozici[$idRole] = Role::zId($idRole)->nazevRole();
            }
        }
        return $jmenaRoliProPozici;
    }

    private function dejPocetPolozekZdarma(Uzivatel $navstevnik, string $castNazvu)
    {
        $polozky            = $navstevnik->finance()->dejPolozkyProBfgr();
        $pocetPolozekZdarma = 0;
        foreach ($polozky as $polozka) {
            ['nazev' => $nazev, 'castka' => $castka] = $polozka;
            if ((float)$castka === 0.0 && mb_stripos($nazev, $castNazvu) !== false) {
                $pocetPolozekZdarma++;
            }
        }
        return $pocetPolozekZdarma;
    }

    private function dejPocetPlacekZdarma(Uzivatel $navstevnik): int
    {
        return $this->dejPocetPolozekZdarma($navstevnik, 'placka');
    }

    private function dejPocetKostekZdarma(Uzivatel $navstevnik): int
    {
        return $this->dejPocetPolozekZdarma($navstevnik, 'kostka');
    }

    private function dejPocetTricekZdarma(Uzivatel $navstevnik): int
    {
        return $this->dejPocetPolozekZdarma($navstevnik, 'tričko');
    }

    private function dejPocetTilekZdarma(Uzivatel $navstevnik): int
    {
        return $this->dejPocetPolozekZdarma($navstevnik, 'tílko');
    }

    private function dejPocetTricekSeSlevou(Uzivatel $navstevnik): int
    {
        return $this->dejPocetPolozekPlacenych($navstevnik, 'tričko modré')
            + $this->dejPocetPolozekPlacenych($navstevnik, 'tričko červené');
    }

    private function dejPocetTilekSeSlevou(Uzivatel $navstevnik): int
    {
        return $this->dejPocetPolozekPlacenych($navstevnik, 'tílko modré')
            + $this->dejPocetPolozekPlacenych($navstevnik, 'tílko červené');
    }

    private function dejPocetTricekPlacenych(Uzivatel $navstevnik): int
    {
        return $this->dejPocetPolozekPlacenych($navstevnik, 'tričko')
            - $this->dejPocetTricekSeSlevou($navstevnik);
    }

    private function dejPocetTilekPlacenych(Uzivatel $navstevnik): int
    {
        return $this->dejPocetPolozekPlacenych($navstevnik, 'tílko')
            - $this->dejPocetTilekSeSlevou($navstevnik);
    }

    private function dejPocetPolozekPlacenych(Uzivatel $navstevnik, string $castNazvu)
    {
        $financniPrehled       = $navstevnik->finance()->dejPolozkyProBfgr();
        $pocetPolozekPlacenych = 0;
        foreach ($financniPrehled as $polozka) {
            ['nazev' => $nazev, 'castka' => $castka] = $polozka;
            if ((float)$castka > 0.0 && mb_stripos($nazev, $castNazvu) !== false) {
                $pocetPolozekPlacenych++;
            }
        }
        return $pocetPolozekPlacenych;
    }

    private function dejPocetPlacekPlacenych(Uzivatel $navstevnik): int
    {
        return $this->dejPocetPolozekPlacenych($navstevnik, 'placka');
    }

    private function dejNazvyAPoctyJidel(Uzivatel $navstevnik, array $moznaJidla): array
    {
        $objednanaJidla = $this->dejNazvyAPoctyPredmetu($navstevnik, implode('|', Jidlo::dejJidlaBehemDne()));
        uksort($objednanaJidla, function (string $nejakeJidloADen, string $jineJidloADen) {
            $nejakeJidloBehemDne = $this->najdiJidloBehemDne($nejakeJidloADen);
            $jineJidloBehemDne   = $this->najdiJidloBehemDne($jineJidloADen);
            $rozdilPoradiJidel   = Jidlo::dejPoradiJidlaBehemDne($nejakeJidloBehemDne) <=> Jidlo::dejPoradiJidlaBehemDne($jineJidloBehemDne);
            if ($rozdilPoradiJidel !== 0) {
                return $rozdilPoradiJidel; // nejdříve chceme řadit podle typu jídla, teprve potom podle dnů
            }
            $denNejakehoJidla = $this->najdiDenVTydnu($nejakeJidloADen);
            $denJinehoJidla   = $this->najdiDenVTydnu($jineJidloADen);
            return DateTimeCz::poradiDne($denNejakehoJidla) <=> DateTimeCz::poradiDne($denJinehoJidla);
        });
        $vsechnaJidlaJakoNeobjednana = array_fill_keys($moznaJidla, 0);
        $vsechnaJidla                = array_merge($vsechnaJidlaJakoNeobjednana, $objednanaJidla);
        return pridejNaZacatekPole('Celkem jídel', array_sum($vsechnaJidla), $vsechnaJidla);
    }

    private function najdiDenVTydnu(string $text): string
    {
        preg_match('~' . $this->dejPoleJakoRegexp(DateTimeCz::dejDnyVTydnu(), '~') . '~uiS', $text, $matches);
        return $matches[0];
    }

    private function najdiJidloBehemDne(string $text): string
    {
        preg_match('~' . $this->dejPoleJakoRegexp(Jidlo::dejJidlaBehemDne(), '~') . '~uiS', $text, $matches);
        return $matches[0];
    }

    /**
     * @param Uzivatel $navstevnik
     * @param string|array $castNazvuRegexpNeboPole
     * @return array
     */
    private function dejNazvyAPoctyPredmetu(Uzivatel $navstevnik, $castNazvuRegexpNeboPole): array
    {
        $castNazvuRegexp = $this->dejPoleJakoRegexp((array)$castNazvuRegexpNeboPole, '~');
        $financniPrehled = $navstevnik->finance()->dejPolozkyProBfgr();
        $poctyPredmetu   = [];
        foreach ($financniPrehled as $polozka) {
            ['nazev' => $nazev, 'pocet' => $pocet] = $polozka;
            if (preg_match('~' . $castNazvuRegexp . '~iS', $nazev)) {
                $poctyPredmetu[$nazev] = ($poctyPredmetu[$nazev] ?? 0) + $pocet;
            }
        }
        return $poctyPredmetu;
    }

    private function dejPoleJakoRegexp(array $retezce, string $delimiter)
    {
        return implode(
            '|',
            array_map(
                static function (string $retezec) use ($delimiter) {
                    return preg_quote($retezec, $delimiter);
                },
                $retezce),
        );
    }

    private function dejNazvyAPoctyCovidTestu(Uzivatel $navstevnik, array $vsechnyMozneCovidTesty): array
    {
        $objednaneCovidTesty = $this->dejNazvyAPoctyPredmetu($navstevnik, 'covid');
        return $this->seradADoplnNenakoupene($objednaneCovidTesty, $vsechnyMozneCovidTesty);
    }

    private function dejNazvyAPoctySvrsku(Uzivatel $navstevnik): array
    {
        $poctySvrsku = [
            'Tričko zdarma'             => $this->dejPocetTricekZdarma($navstevnik),
            'Tílko zdarma'              => $this->dejPocetTilekZdarma($navstevnik),
            'Tričko se slevou'          => $this->dejPocetTricekSeSlevou($navstevnik),
            'Tílko se slevou'           => $this->dejPocetTilekSeSlevou($navstevnik),
            'Účastnické tričko placené' => $this->dejPocetTricekPlacenych($navstevnik),
            'Účastnické tílko placené'  => $this->dejPocetTilekPlacenych($navstevnik),
        ];
        return pridejNaZacatekPole('Celkem svršků', array_sum($poctySvrsku), $poctySvrsku);
    }

    private function dejNazvyAPoctyOstatnichPredmetu(Uzivatel $navstevnik, array $vsechnyMozneOstatniPredmety): array
    {
        $objednaneOstatniPredmety = $this->dejNazvyAPoctyPredmetu($navstevnik, $vsechnyMozneOstatniPredmety);
        return $this->seradADoplnNenakoupene($objednaneOstatniPredmety, $vsechnyMozneOstatniPredmety);
    }

    private function seradADoplnNenakoupene(array $objednaneSPocty, array $vsechnyMozneJenNazvy): array
    {
        $vsechnyMozneJakoNeobjednane = array_fill_keys($vsechnyMozneJenNazvy, 0); // zachová pořadí
        $objednaneANeobjednane       = array_merge( // zachová pořadí
            $vsechnyMozneJakoNeobjednane,
            $objednaneSPocty,
        );
        if (count($objednaneANeobjednane) !== count($vsechnyMozneJenNazvy)) {
            throw new \RuntimeException(
                'Neznámé položky ' . implode(array_keys(array_diff_key($objednaneSPocty, $vsechnyMozneJakoNeobjednane)))
            );
        }
        return $objednaneANeobjednane;
    }

    private function dejNazvyAPoctyPlacek(Uzivatel $navstevnik): array
    {
        $poctyPlacek = [
            'Placka zdarma'     => $this->dejPocetPlacekZdarma($navstevnik),
            'Placka GC placená' => $this->dejPocetPlacekPlacenych($navstevnik),
        ];
        return pridejNaZacatekPole('Celkem placek', array_sum($poctyPlacek), $poctyPlacek);
    }

    private function dejNazvyAPoctyKostek(Uzivatel $navstevnik, array $vsechnyMozneKostky): array
    {
        $objednaneKostky = $this->dejNazvyAPoctyPredmetu($navstevnik, 'kostka');
        foreach ($objednaneKostky as $objednanaKostka => $pocet) {
            if (!preg_match('~ \d{4}$~', $objednanaKostka)) {
                unset($objednaneKostky[$objednanaKostka]);
                $objednaneKostky[$objednanaKostka . ' ' . $this->systemoveNastaveni->rocnik()] = $pocet;
            }
        }
        $poctyKostek = $this->seradADoplnNenakoupene($objednaneKostky, $vsechnyMozneKostky);
        // pozor, kostky zdarma je počet kostek z výše uvedených objednaných (podmnožina) - nejsou to kostky navíc
        $poctyKostek['Kostka zdarma'] = $this->dejPocetKostekZdarma($navstevnik);
        return pridejNaZacatekPole('Celkem kostek', array_sum($objednaneKostky), $poctyKostek);
    }

    private function letosniPlacky(): array
    {
        return dbFetchPairs(<<<SQL
            SELECT id_predmetu, CONCAT_WS(' ', TRIM(nazev), model_rok)
            FROM shop_predmety
            WHERE nazev LIKE '%placka%' COLLATE utf8_czech_ci
                AND stav > $0
            SQL,
            [0 => StavPredmetu::MIMO],
        );
    }

    private function letosniKostky(): array
    {
        $poradiKostek    = [
            'kostka zdarma',
            'Kostka Cthulhu 2021',
            'Fate kostka 2021',
            'Kostka 2018',
            'Kostka 2012',
        ];
        $poradiKostekSql = implode(',', $poradiKostek);

        return dbFetchPairs(<<<SQL
            SELECT id_predmetu, CONCAT_WS(' ', TRIM(nazev), model_rok)
            FROM shop_predmety
            WHERE nazev LIKE '%kostka%' COLLATE utf8_czech_ci
                AND stav > $0
                AND typ = $1
            ORDER BY FIND_IN_SET(CONCAT_WS(' ', TRIM(nazev), model_rok), '{$poradiKostekSql}')
            SQL,
            [0 => StavPredmetu::MIMO, 1 => TypPredmetu::PREDMET],
        );
    }

    private function letosniJidla(): array
    {
        return dbFetchPairs(<<<SQL
            SELECT id_predmetu, TRIM(nazev)
            FROM shop_predmety
            WHERE typ = $0
                AND model_rok = {$this->systemoveNastaveni->rocnik()}
            ORDER BY FIELD(SUBSTRING(TRIM(nazev), 1, POSITION(' ' IN TRIM(nazev)) - 1), 'Snídaně', 'Oběd', 'Večeře'),
                     FIELD(SUBSTRING(TRIM(nazev), POSITION(' ' IN TRIM(nazev)) + 1), 'středa', 'čtvrtek', 'pátek', 'sobota', 'neděle')
            SQL,
            [0 => TypPredmetu::JIDLO],
        );
    }

    private function letosniOstatniPredmety(): array
    {
        return dbFetchPairs(<<<SQL
            SELECT id_predmetu,
                   IF(model_rok != {$this->systemoveNastaveni->rocnik()},
                       CONCAT_WS(' ', TRIM(nazev), model_rok),
                       nazev
                   ) AS nazev
            FROM shop_predmety
            WHERE typ = $0
                AND stav > $1
                AND (nazev = 'nicknack' COLLATE utf8_czech_ci
                         OR nazev LIKE '%ponožky%' COLLATE utf8_czech_ci
                         OR nazev LIKE '%lok%' COLLATE utf8_czech_ci
                         OR nazev LIKE '%taška%' COLLATE utf8_czech_ci
                    )
            ORDER BY TRIM(nazev)
            SQL,
            [0 => TypPredmetu::PREDMET, 1 => StavPredmetu::MIMO],
        );
    }

    private function letosniCovidTesty(): array
    {
        return dbFetchPairs(<<<SQL
            SELECT id_predmetu, TRIM(nazev)
            FROM shop_predmety
            WHERE typ = $0
                AND stav > $1
                AND TRIM(nazev) LIKE '%COVID%' COLLATE utf8_czech_ci
            ORDER BY TRIM(nazev)
            SQL,
            [0 => TypPredmetu::PREDMET, 1 => StavPredmetu::MIMO],
        );
    }
}
