<?php

namespace Gamecon\Tests\Uzivatel;

use Gamecon\Cas\DateTimeCz;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Role\Role;
use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\DbTest;
use Gamecon\Uzivatel\Exceptions\NaHromadneOdhlasovaniJeBrzy;
use Gamecon\Uzivatel\Exceptions\NaHromadneOdhlasovaniJePozde;
use Gamecon\Uzivatel\HromadneOdhlaseniNeplaticu;
use Gamecon\Uzivatel\KategorieNeplatice;
use Granam\RemoveDiacritics\RemoveDiacritics;

class HromadneOdhlaseniNeplaticuTest extends DbTest
{
    private const ID_NAHODNEHO_PREDMETU = 111;
    private const ID_PREDMETU_UBYTOVANI = 112;

    private const VELKY_DLUH_NIC_NEDAM               = 222;
    private const VELKY_DLUH_DAM_MALO                = 223;
    private const VELKY_DLUH_DAM_MALO_ODHLASIME_CAST = 224;
    private const VELKY_DLUH_NIC_NEDAM_NEODHLASOVAT  = 2220;
    private const VELKY_DLUH_DAM_MALO_NEODHLASOVAT   = 2230;
    private const VELKY_DLUH_NIC_NEDAM_LETOS_NEJSEM  = 2221;
    private const VELKY_DLUH_DAM_MALO_LETOS_NEJSEM   = 2231;

    protected static bool $disableStrictTransTables = true;

    protected static function getInitQueries(): array {
        $systemoveNastaveni = SystemoveNastaveni::vytvorZGlobals();

        $queries[] = self::nejakyPredmetQuery($systemoveNastaveni);
        $queries[] = self::predmetUbytovani($systemoveNastaveni);

//        // očekávaná kategorie neplatiče 1 LETOS_NEPOSLAL_NIC_A_LONI_NIC_NEBO_MA_VELKY_DLUH
//        $queries[] = self::uzivatelQuery(self::VELKY_DLUH_NIC_NEDAM, 'Velký dluh', 'Nic nedám');
//        $queries[] = self::prihlasenNaLetosniGcVcasQuery(self::VELKY_DLUH_NIC_NEDAM);
//        $queries[] = self::nakupProVelkyDluhQuery(self::VELKY_DLUH_NIC_NEDAM, $systemoveNastaveni);
//
//        // očekávaná kategorie neplatiče 2 LETOS_POSLAL_MALO_A_MA_VELKY_DLUH
//        $queries[] = self::uzivatelQuery(self::VELKY_DLUH_DAM_MALO, 'Velký dluh', 'Dám málo');
//        $queries[] = self::prihlasenNaLetosniGcVcasQuery(self::VELKY_DLUH_DAM_MALO);
//        $queries[] = self::nakupProVelkyDluhQuery(self::VELKY_DLUH_DAM_MALO, $systemoveNastaveni);
//        $queries[] = self::poslalMaloQuery(self::VELKY_DLUH_DAM_MALO, $systemoveNastaveni);

        // očekávaná kategorie neplatiče 2 LETOS_POSLAL_MALO_A_MA_VELKY_DLUH postupné odhlašování
        $queries[] = self::uzivatelQuery(self::VELKY_DLUH_DAM_MALO_ODHLASIME_CAST, 'Velký dluh', 'Dám málo, Odhlásíme část');
        $queries[] = self::prihlasenNaLetosniGcVcasQuery(self::VELKY_DLUH_DAM_MALO_ODHLASIME_CAST);
        $queries[] = self::poslalMaloQuery(self::VELKY_DLUH_DAM_MALO_ODHLASIME_CAST, $systemoveNastaveni);
        $queries[] = self::nakupUbytovaniQuery(self::VELKY_DLUH_DAM_MALO_ODHLASIME_CAST, $systemoveNastaveni, 1.0);
        $queries[] = self::nakupProVelkyDluhQuery(self::VELKY_DLUH_DAM_MALO_ODHLASIME_CAST, $systemoveNastaveni, -0.1 /* zrušením ubytování už tohle nebude velký dluh */);

//        $queries[] = self::uzivatelQuery(self::VELKY_DLUH_NIC_NEDAM_NEODHLASOVAT, 'Velký dluh', 'Nic nedám, Neodhlašovat');
//        $queries[] = self::prihlasenNaLetosniGcVcasQuery(self::VELKY_DLUH_NIC_NEDAM_NEODHLASOVAT);
//        $queries[] = self::nakupProVelkyDluhQuery(self::VELKY_DLUH_NIC_NEDAM_NEODHLASOVAT, $systemoveNastaveni);
//        $queries[] = self::neodhlasovatQuery(self::VELKY_DLUH_NIC_NEDAM_NEODHLASOVAT);
//
//        $queries[] = self::uzivatelQuery(self::VELKY_DLUH_DAM_MALO_NEODHLASOVAT, 'Velký dluh', 'Dám málo, Neodhlašovat');
//        $queries[] = self::prihlasenNaLetosniGcVcasQuery(self::VELKY_DLUH_DAM_MALO_NEODHLASOVAT);
//        $queries[] = self::nakupProVelkyDluhQuery(self::VELKY_DLUH_DAM_MALO_NEODHLASOVAT, $systemoveNastaveni);
//        $queries[] = self::poslalMaloQuery(self::VELKY_DLUH_DAM_MALO_NEODHLASOVAT, $systemoveNastaveni);
//        $queries[] = self::neodhlasovatQuery(self::VELKY_DLUH_DAM_MALO_NEODHLASOVAT);
//
//        $queries[] = self::uzivatelQuery(self::VELKY_DLUH_NIC_NEDAM_LETOS_NEJSEM, 'Velký dluh', 'Nic nedám, Letos nejsem');
//        $queries[] = self::nakupProVelkyDluhQuery(self::VELKY_DLUH_NIC_NEDAM_LETOS_NEJSEM, $systemoveNastaveni);
//
//        $queries[] = self::uzivatelQuery(self::VELKY_DLUH_DAM_MALO_LETOS_NEJSEM, 'Velký dluh', 'Dám málo, Letos nejsem');
//        $queries[] = self::nakupProVelkyDluhQuery(self::VELKY_DLUH_DAM_MALO_LETOS_NEJSEM, $systemoveNastaveni);
//        $queries[] = self::poslalMaloQuery(self::VELKY_DLUH_DAM_MALO_LETOS_NEJSEM, $systemoveNastaveni);

        return $queries;
    }

    private static function predmetUbytovani(SystemoveNastaveni $systemoveNastaveni): string {
        return self::predmetQuery(
            self::ID_PREDMETU_UBYTOVANI,
            TypPredmetu::VSTUPNE,
            'luxusní 0+KK',
            $systemoveNastaveni
        );
    }

    private static function nejakyPredmetQuery(SystemoveNastaveni $systemoveNastaveni): string {
        return self::predmetQuery(
            self::ID_NAHODNEHO_PREDMETU,
            TypPredmetu::VSTUPNE,
            'cosi kdesi',
            $systemoveNastaveni
        );
    }

    private static function predmetQuery(
        int                $idPredmetu,
        int                $typPredmetu,
        string             $nazev,
        SystemoveNastaveni $systemoveNastaveni
    ): string {
        $rok = $systemoveNastaveni->rocnik();

        return <<<SQL
INSERT INTO shop_predmety
SET id_predmetu = $idPredmetu,
    nazev = '$nazev',
    typ = $typPredmetu,
    model_rok = $rok,
    cena_aktualni = 0.0 -- nemá na nic vliv, "nákup" řešíme přímým zápisem do DB včetně vlastní podejní ceny
SQL;
    }

    private static function uzivatelQuery(int $idUzivatele, string $jmeno, string $prijmeni): string {
        $login = RemoveDiacritics::toSnakeCaseId("$jmeno $prijmeni");
        $email = str_replace('_', '.', $login) . '@dot.com';
        return <<<SQL
INSERT INTO uzivatele_hodnoty SET id_uzivatele = $idUzivatele, login_uzivatele = '$login', jmeno_uzivatele = '$jmeno', prijmeni_uzivatele = '$prijmeni', email1_uzivatele = '$email'
SQL;
    }

    private static function prihlasenNaLetosniGcVcasQuery(int $idUzivatele): string {
        $rolePrihlasenNaLetosniGc = Role::PRIHLASEN_NA_LETOSNI_GC;
        $uzivatelSystem           = \Uzivatel::SYSTEM;
        $posazen                  = (new \DateTimeImmutable('-2 weeks'))->format(DateTimeCz::FORMAT_DB);

        return <<<SQL
INSERT INTO uzivatele_role(id_uzivatele, id_role, posadil, posazen)
VALUES ($idUzivatele, $rolePrihlasenNaLetosniGc, $uzivatelSystem, '$posazen')
SQL;
    }

    private static function nakupProVelkyDluhQuery(
        int                $idUzivatele,
        SystemoveNastaveni $systemoveNastaveni,
        float              $upravaCeny = 0.0
    ): string {
        $rok                          = $systemoveNastaveni->rocnik();
        $velkyDluh                    = $systemoveNastaveni->neplaticCastkaVelkyDluh();
        $poslalMalo                   = self::poslalMalo($systemoveNastaveni);
        $cena                         = $velkyDluh + $poslalMalo + $upravaCeny;
        $idPredmetuSCenouVelkehoDluhu = self::ID_NAHODNEHO_PREDMETU;

        return <<<SQL
INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni)
VALUES ($idUzivatele, $idPredmetuSCenouVelkehoDluhu, $rok, $cena)
SQL;
    }

    private static function nakupUbytovaniQuery(
        int                $idUzivatele,
        SystemoveNastaveni $systemoveNastaveni,
        float              $cena
    ): string {
        $rok                 = $systemoveNastaveni->rocnik();
        $idPredmetuUbytovani = self::ID_PREDMETU_UBYTOVANI;

        return <<<SQL
INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni)
VALUES ($idUzivatele, $idPredmetuUbytovani, $rok, $cena)
SQL;
    }

    private static function nakupQuery(
        int $idUzivatele,

    ) {
        return <<<SQL
INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni)
VALUES ($idUzivatele, $idPredmetuUbytovani, $rok, $cena)
SQL;
    }

    private static function poslalMaloQuery(int $idUzivatele, SystemoveNastaveni $systemoveNastaveni): string {
        $rok            = $systemoveNastaveni->rocnik();
        $poslalMalo     = self::poslalMalo($systemoveNastaveni);
        $uzivatelSystem = \Uzivatel::SYSTEM;

        return <<<SQL
INSERT INTO platby SET id_uzivatele = $idUzivatele, rok = {$rok}, castka = {$poslalMalo}, provedl = {$uzivatelSystem}
SQL;
    }

    private static function poslalMalo(SystemoveNastaveni $systemoveNastaveni): float {
        return (float)$systemoveNastaveni->neplaticCastkaPoslalDost() - 0.01;
    }

    private static function neodhlasovatQuery(int $idUzivatele): string {
        $roleNeodhlasovat = Role::LETOSNI_NEODHLASOVAT;
        $uzivatelSystem   = \Uzivatel::SYSTEM;

        return <<<SQL
INSERT INTO uzivatele_role(id_uzivatele, id_role, posadil)
VALUES ($idUzivatele, $roleNeodhlasovat, $uzivatelSystem)
SQL;
    }

    /**
     * @test
     */
    public function Muzu_zalogovat_notifikovani_neplaticu_o_brzkem_hromadnem_odhlaseni_a_zpetne_precist_kdy() {
        $hromadneOdhlaseniNeplaticu = new HromadneOdhlaseniNeplaticu(SystemoveNastaveni::vytvorZGlobals());

        $hromadneOdhlasovaniKdy = new \DateTimeImmutable('2023-05-01 01:01:01');
        $staloSeKdy             = new \DateTimeImmutable('2023-05-01 10:11:12');
        $poradiOznameni         = 1;
        $hromadneOdhlaseniNeplaticu->zalogujNotifikovaniNeplaticuOBrzkemHromadnemOdhlaseni(
            123,
            $hromadneOdhlasovaniKdy,
            $poradiOznameni,
            \Uzivatel::zIdUrcite(1),
            $staloSeKdy
        );
        $kdyZLogu = $hromadneOdhlaseniNeplaticu->neplaticiNotifikovaniOBrzkemHromadnemOdhlaseniKdy(
            $hromadneOdhlasovaniKdy,
            $poradiOznameni
        );
        self::assertEquals($staloSeKdy, $kdyZLogu);
    }

    /**
     * @test
     */
    public function Muzu_zalogovat_notifikovani_cfo_o_brzkem_hromadnem_odhlaseni_a_zpetne_precist_kdy() {
        $hromadneOdhlaseniNeplaticu = new HromadneOdhlaseniNeplaticu(SystemoveNastaveni::vytvorZGlobals());

        $hromadneOdhlasovaniKdy = new \DateTimeImmutable('2023-06-02 02:02:02');
        $staloSeKdy             = new \DateTimeImmutable('2023-06-02 03:04:05');
        $poradiOznameni         = 1;
        $hromadneOdhlaseniNeplaticu->zalogujNotifikovaniCfoOBrzkemHromadnemOdhlaseni(
            123,
            $hromadneOdhlasovaniKdy,
            $poradiOznameni,
            \Uzivatel::zIdUrcite(1),
            $staloSeKdy
        );
        $kdyZLogu = $hromadneOdhlaseniNeplaticu->cfoNotifikovanOBrzkemHromadnemOdhlaseniKdy(
            $hromadneOdhlasovaniKdy,
            $poradiOznameni
        );
        self::assertEquals($staloSeKdy, $kdyZLogu);
    }

    /**
     * @test
     */
    public function Nemuzu_ziskat_neplatice_kdyz_nejblizsi_odhlasovani_teprve_bude() {
        $systemoveNastaveni                    = SystemoveNastaveni::vytvorZGlobals();
        $hromadneOdhlaseniNeplaticu            = new HromadneOdhlaseniNeplaticu(SystemoveNastaveni::vytvorZGlobals());
        $nejblizsiHromadneOdhlasovaniVBudoucnu = $systemoveNastaveni->ted()->modify('+1 second');

        self::expectException(NaHromadneOdhlasovaniJeBrzy::class);
        $generator = $hromadneOdhlaseniNeplaticu->neplaticiAKategorie($nejblizsiHromadneOdhlasovaniVBudoucnu);
        $generator->next();
    }

    /**
     * @test
     */
    public function Nemuzu_ziskat_neplatice_kdyz_okno_pro_nejblizsi_odhlasovani_uz_bylo() {
        $systemoveNastaveni                = SystemoveNastaveni::vytvorZGlobals();
        $hromadneOdhlaseniNeplaticu        = new HromadneOdhlaseniNeplaticu(SystemoveNastaveni::vytvorZGlobals());
        $nejblizsiHromadneOdhlasovaniVcera = $systemoveNastaveni->ted()->modify('-1 day -1 second');

        self::expectException(NaHromadneOdhlasovaniJePozde::class);
        $generator = $hromadneOdhlaseniNeplaticu->neplaticiAKategorie($nejblizsiHromadneOdhlasovaniVcera);
        $generator->next();
    }

    /**
     * Toto by se nemělo nidky stát. Ale známe ta "nikdy"...
     * @test
     */
    public function Nemuzu_ziskat_neplatice_kdyz_cas_pro_odhlasovani_je_zaroven_s_vlnou_aktivit() {
        $nejblizsiHromadneOdhlasovaniKdy = new \DateTimeImmutable();
        $systemoveNastaveni              = $this->dejSystemoveNastaveniSNejblizsiVlnou(
            DateTimeGamecon::createFromInterface($nejblizsiHromadneOdhlasovaniKdy)
        );

        self::assertEquals(
            $nejblizsiHromadneOdhlasovaniKdy,
            $systemoveNastaveni->nejblizsiVlnaKdy(),
            'Pro tento negativní test potřebujeme datum odhlašování stejné jako nejbližší vlnu aktivit'
        );

        $hromadneOdhlaseniNeplaticu = new HromadneOdhlaseniNeplaticu($systemoveNastaveni);

        self::expectException(NaHromadneOdhlasovaniJePozde::class);

        $generator = $hromadneOdhlaseniNeplaticu->neplaticiAKategorie($nejblizsiHromadneOdhlasovaniKdy);
        $generator->next();
    }

    private function dejSystemoveNastaveniSNejblizsiVlnou(DateTimeGamecon $nejblizsiVlnaKdy): SystemoveNastaveni {
        return new class($nejblizsiVlnaKdy) extends SystemoveNastaveni {
            public function __construct(private readonly DateTimeGamecon $nejblizsiVlnaKdy) {
            }

            public function nejblizsiVlnaKdy(\DateTimeInterface $platnostZpetneKDatu = null): DateTimeGamecon {
                return $this->nejblizsiVlnaKdy;
            }

            public function ted(): DateTimeImmutableStrict {
                return new DateTimeImmutableStrict();
            }

            public function rocnik(): int {
                return self::ROCNIK;
            }
        };
    }

    /**
     * Toto by se nemělo nidky stát. Ale známe ta "nikdy"...
     * @test
     */
    public function Nemuzu_ziskat_neplatice_kdyz_cas_pro_odhlasovani_je_az_po_vlne_aktivit() {
        $nejblizsiHromadneOdhlasovaniKdy = new \DateTimeImmutable();
        $nejblizsiVlnaOdhlasovani        = DateTimeGamecon::createFromInterface($nejblizsiHromadneOdhlasovaniKdy)
            ->modify('-1 second');
        $ted                             = new DateTimeImmutableStrict();
        $systemoveNastaveni              = $this->dejSystemoveNastaveniSNejblizsiVlnou($nejblizsiVlnaOdhlasovani);
        $platnostZpetneKDatu             ??= $ted->modify('-1 day');

        self::assertGreaterThan(
            $systemoveNastaveni->nejblizsiVlnaKdy($platnostZpetneKDatu),
            $nejblizsiHromadneOdhlasovaniKdy,
            'Pro tento negativní test potřebujeme datum odhlašování až po nejbližší vlně aktivit'
        );

        $hromadneOdhlaseniNeplaticu = new HromadneOdhlaseniNeplaticu($systemoveNastaveni);

        self::expectException(NaHromadneOdhlasovaniJePozde::class);
        $generator = $hromadneOdhlaseniNeplaticu->neplaticiAKategorie(
            $nejblizsiHromadneOdhlasovaniKdy,
            $ted,
            $platnostZpetneKDatu
        );
        $generator->next();
    }

    /**
     * @test
     */
    public function Dostanu_spravne_uzivatele_ke_kontrole() {
        $nejblizsiHromadneOdhlasovaniKdy = new \DateTimeImmutable();
        $nejblizsiVlnaOdhlasovani        = DateTimeGamecon::createFromInterface($nejblizsiHromadneOdhlasovaniKdy)
            ->modify('+1 day');
        $ted                             = new DateTimeImmutableStrict();
        $systemoveNastaveni              = $this->dejSystemoveNastaveniSNejblizsiVlnou($nejblizsiVlnaOdhlasovani);
        $platnostZpetneKDatu             ??= $ted->modify('-1 day');

        self::assertLessThan(
            $systemoveNastaveni->nejblizsiVlnaKdy($platnostZpetneKDatu),
            $nejblizsiHromadneOdhlasovaniKdy,
            'Pro tento test potřebujeme datum odhlašování před nejbližší vlnou aktivit'
        );

        $hromadneOdhlaseniNeplaticu = new HromadneOdhlaseniNeplaticu($systemoveNastaveni);
        $generator                  = $hromadneOdhlaseniNeplaticu->neplaticiAKategorie(
            $nejblizsiHromadneOdhlasovaniKdy,
            $ted,
            $platnostZpetneKDatu
        );
        $neplaticiAKategorieScalar  = [];
        foreach ($generator as $zaznam) {
            self::assertInstanceOf(\Uzivatel::class, $zaznam['neplatic']);
            /** @var \Uzivatel $neplatic */
            $neplatic = $zaznam['neplatic'];
            self::assertInstanceOf(KategorieNeplatice::class, $zaznam['kategorie_neplatice']);
            /** @var KategorieNeplatice $kategorieNeplatice */
            $kategorieNeplatice          = $zaznam['kategorie_neplatice'];
            $neplaticiAKategorieScalar[] = [
                'neplatic'            => $neplatic->id(),
                'kategorie_neplatice' => $kategorieNeplatice->ciselnaKategoriiNeplatice(),
            ];
        }
        usort(
            $neplaticiAKategorieScalar,
            static fn(array $nejakyZaznam, array $jinyZanam) => $nejakyZaznam['neplatic'] <=> $jinyZanam['neplatic']
        );
        self::assertSame(
            [
//                [
//                    'neplatic'            => self::VELKY_DLUH_NIC_NEDAM,
//                    'kategorie_neplatice' => KategorieNeplatice::LETOS_NEPOSLAL_NIC_A_LONI_NIC_NEBO_MA_VELKY_DLUH,
//                ],
//                [
//                    'neplatic'            => self::VELKY_DLUH_DAM_MALO,
//                    'kategorie_neplatice' => KategorieNeplatice::LETOS_POSLAL_MALO_A_MA_VELKY_DLUH,
//                ],
                [
                    'neplatic'            => self::VELKY_DLUH_DAM_MALO_ODHLASIME_CAST,
                    'kategorie_neplatice' => KategorieNeplatice::LETOS_POSLAL_MALO_A_MA_VELKY_DLUH,
                ],
            ],
            $neplaticiAKategorieScalar
        );
    }

}
