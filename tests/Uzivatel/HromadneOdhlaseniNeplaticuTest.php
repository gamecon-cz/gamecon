<?php

namespace Gamecon\Tests\Uzivatel;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Logger\Zaznamnik;
use Gamecon\Role\Role;
use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\Exceptions\HromadneOdhlasovaniJePrilisBrzyPoVlne;
use Gamecon\Uzivatel\Exceptions\NaHromadneOdhlasovaniJeBrzy;
use Gamecon\Uzivatel\Exceptions\NaHromadneOdhlasovaniJePozde;
use Gamecon\Uzivatel\HromadneOdhlaseniNeplaticu;
use Gamecon\Uzivatel\KategorieNeplatice;
use Granam\RemoveDiacritics\RemoveDiacritics;
use function PHPUnit\Framework\assertCount;

class HromadneOdhlaseniNeplaticuTest extends AbstractTestDb
{
    private const ID_NAHODNEHO_PREDMETU = 100;
    private const ID_PREDMETU_UBYTOVANI = 101;
    private const ID_LARP_AKTIVITY      = 1000;
    private const ID_RPG_AKTIVITY       = 1010;
    private const ID_JINE_AKTIVITY      = 1020;

    private const VELKY_DLUH_NIC_NEDAM                   = 200;
    private const VELKY_DLUH_DAM_MALO                    = 201;
    private const VELKY_DLUH_DAM_MALO_ODHLASTE_UBYTOVANI = 202;
    private const VELKY_DLUH_DAM_MALO_ODHLASTE_AKTIVITY  = 203;
    private const VELKY_DLUH_NIC_NEDAM_NEODHLASOVAT      = 2010;
    private const VELKY_DLUH_DAM_MALO_NEODHLASOVAT       = 2220;
    private const VELKY_DLUH_NIC_NEDAM_LETOS_NEJSEM      = 2230;
    private const VELKY_DLUH_DAM_MALO_LETOS_NEJSEM       = 2240;

    private const LETOS_PRIHLASENI_UZIVATELE = [
        self::VELKY_DLUH_NIC_NEDAM,
        self::VELKY_DLUH_DAM_MALO,
        self::VELKY_DLUH_DAM_MALO_ODHLASTE_UBYTOVANI,
        self::VELKY_DLUH_DAM_MALO_ODHLASTE_AKTIVITY,
        self::VELKY_DLUH_NIC_NEDAM_NEODHLASOVAT,
        self::VELKY_DLUH_DAM_MALO_NEODHLASOVAT,
    ];

    protected static bool $disableStrictTransTables = true;

    protected static function getInitQueries(): array
    {
        $systemoveNastaveni = SystemoveNastaveni::vytvorZGlobals();

        $queries[] = self::nejakyPredmetQuery($systemoveNastaveni);
        $queries[] = self::predmetUbytovaniQuery($systemoveNastaveni);
        $queries[] = self::aktivitaLarpQuery($systemoveNastaveni, $cenaLarpu = 11.1);
        $queries[] = self::aktivitaRpgQuery($systemoveNastaveni, $cenaRpg = 22.2);
        $queries[] = self::aktivitaJinaAktvitaQuery($systemoveNastaveni, $cenaJineAkivity = 33.3);

        // očekávaná kategorie neplatiče 1 LETOS_NEPOSLAL_NIC_A_LONI_NIC_NEBO_MA_VELKY_DLUH
        $queries[] = self::uzivatelQuery(self::VELKY_DLUH_NIC_NEDAM, 'Velký dluh', 'Nic nedám');
        $queries[] = self::prihlasenNaLetosniGcVcasQuery(self::VELKY_DLUH_NIC_NEDAM);
        $queries[] = self::nakupProVelkyDluhQuery(self::VELKY_DLUH_NIC_NEDAM, $systemoveNastaveni);

        // očekávaná kategorie neplatiče 2 LETOS_POSLAL_MALO_A_MA_VELKY_DLUH
        $queries[] = self::uzivatelQuery(self::VELKY_DLUH_DAM_MALO, 'Velký dluh', 'Dám málo');
        $queries[] = self::prihlasenNaLetosniGcVcasQuery(self::VELKY_DLUH_DAM_MALO);
        $queries[] = self::nakupProVelkyDluhQuery(self::VELKY_DLUH_DAM_MALO, $systemoveNastaveni);
        $queries[] = self::poslalMaloQuery(self::VELKY_DLUH_DAM_MALO, $systemoveNastaveni);

        // očekávaná kategorie neplatiče 2 LETOS_POSLAL_MALO_A_MA_VELKY_DLUH postupné odhlašování
        $queries[] = self::uzivatelQuery(self::VELKY_DLUH_DAM_MALO_ODHLASTE_UBYTOVANI, 'Velký dluh', 'Dám málo, Odhlašte ubytování');
        $queries[] = self::prihlasenNaLetosniGcVcasQuery(self::VELKY_DLUH_DAM_MALO_ODHLASTE_UBYTOVANI);
        $queries[] = self::poslalMaloQuery(self::VELKY_DLUH_DAM_MALO_ODHLASTE_UBYTOVANI, $systemoveNastaveni);
        $queries[] = self::nakupUbytovaniQuery(self::VELKY_DLUH_DAM_MALO_ODHLASTE_UBYTOVANI, $systemoveNastaveni, 1.0);
        $queries[] = self::nakupProVelkyDluhQuery(self::VELKY_DLUH_DAM_MALO_ODHLASTE_UBYTOVANI, $systemoveNastaveni, -0.1 /* zrušením ubytování už tohle nebude velký dluh */);

        // očekávaná kategorie neplatiče 2 LETOS_POSLAL_MALO_A_MA_VELKY_DLUH postupné odhlašování
        $queries[] = self::uzivatelQuery(self::VELKY_DLUH_DAM_MALO_ODHLASTE_AKTIVITY, 'Velký dluh', 'Dám málo, Odhlašte aktivity');
        $queries[] = self::prihlasenNaLetosniGcVcasQuery(self::VELKY_DLUH_DAM_MALO_ODHLASTE_AKTIVITY);
        $queries[] = self::poslalMaloQuery(self::VELKY_DLUH_DAM_MALO_ODHLASTE_AKTIVITY, $systemoveNastaveni);
        $queries[] = self::prihlaseniNaLarpQuery(self::VELKY_DLUH_DAM_MALO_ODHLASTE_AKTIVITY);
        $queries[] = self::prihlaseniNaRpgQuery(self::VELKY_DLUH_DAM_MALO_ODHLASTE_AKTIVITY);
        $queries[] = self::prihlaseniNaJinouAktivituQuery(self::VELKY_DLUH_DAM_MALO_ODHLASTE_AKTIVITY);
        $queries[] = self::nakupProVelkyDluhQuery(
            self::VELKY_DLUH_DAM_MALO_ODHLASTE_AKTIVITY,
            $systemoveNastaveni,
            -$cenaLarpu - $cenaRpg - 0.1 /* zrušením některých aktivit už tohle nebude velký dluh */);

        $queries[] = self::uzivatelQuery(self::VELKY_DLUH_NIC_NEDAM_NEODHLASOVAT, 'Velký dluh', 'Nic nedám, Neodhlašovat');
        $queries[] = self::prihlasenNaLetosniGcVcasQuery(self::VELKY_DLUH_NIC_NEDAM_NEODHLASOVAT);
        $queries[] = self::nakupProVelkyDluhQuery(self::VELKY_DLUH_NIC_NEDAM_NEODHLASOVAT, $systemoveNastaveni);
        $queries[] = self::neodhlasovatQuery(self::VELKY_DLUH_NIC_NEDAM_NEODHLASOVAT);

        $queries[] = self::uzivatelQuery(self::VELKY_DLUH_DAM_MALO_NEODHLASOVAT, 'Velký dluh', 'Dám málo, Neodhlašovat');
        $queries[] = self::prihlasenNaLetosniGcVcasQuery(self::VELKY_DLUH_DAM_MALO_NEODHLASOVAT);
        $queries[] = self::nakupProVelkyDluhQuery(self::VELKY_DLUH_DAM_MALO_NEODHLASOVAT, $systemoveNastaveni);
        $queries[] = self::poslalMaloQuery(self::VELKY_DLUH_DAM_MALO_NEODHLASOVAT, $systemoveNastaveni);
        $queries[] = self::neodhlasovatQuery(self::VELKY_DLUH_DAM_MALO_NEODHLASOVAT);

        $queries[] = self::uzivatelQuery(self::VELKY_DLUH_NIC_NEDAM_LETOS_NEJSEM, 'Velký dluh', 'Nic nedám, Letos nejsem');
        $queries[] = self::nakupProVelkyDluhQuery(self::VELKY_DLUH_NIC_NEDAM_LETOS_NEJSEM, $systemoveNastaveni);

        $queries[] = self::uzivatelQuery(self::VELKY_DLUH_DAM_MALO_LETOS_NEJSEM, 'Velký dluh', 'Dám málo, Letos nejsem');
        $queries[] = self::nakupProVelkyDluhQuery(self::VELKY_DLUH_DAM_MALO_LETOS_NEJSEM, $systemoveNastaveni);
        $queries[] = self::poslalMaloQuery(self::VELKY_DLUH_DAM_MALO_LETOS_NEJSEM, $systemoveNastaveni);

        return $queries;
    }

    private static function predmetUbytovaniQuery(SystemoveNastaveni $systemoveNastaveni): string
    {
        return self::predmetQuery(
            self::ID_PREDMETU_UBYTOVANI,
            TypPredmetu::UBYTOVANI,
            'luxusní 0+KK',
            $systemoveNastaveni,
        );
    }

    private static function nejakyPredmetQuery(SystemoveNastaveni $systemoveNastaveni): string
    {
        return self::predmetQuery(
            self::ID_NAHODNEHO_PREDMETU,
            // pozor dvoje vstupné logika ignoruje, jako "koupený předmět" se použije jen jedno
            TypPredmetu::VSTUPNE,
            'cosi kdesi',
            $systemoveNastaveni,
        );
    }

    private static function predmetQuery(
        int                $idPredmetu,
        int                $typPredmetu,
        string             $nazev,
        SystemoveNastaveni $systemoveNastaveni,
    ): string
    {
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

    private static function aktivitaLarpQuery(SystemoveNastaveni $systemoveNastaveni, float $cena): string
    {
        return self::aktivitaQuery(
            self::ID_LARP_AKTIVITY,
            TypAktivity::LARP,
            'Na dudlíky',
            $cena,
            $systemoveNastaveni,
        );
    }

    private static function aktivitaRpgQuery(SystemoveNastaveni $systemoveNastaveni, float $cena): string
    {
        return self::aktivitaQuery(
            self::ID_RPG_AKTIVITY,
            TypAktivity::RPG,
            'Dračí poupě',
            $cena,
            $systemoveNastaveni,
        );
    }

    private static function aktivitaJinaAktvitaQuery(SystemoveNastaveni $systemoveNastaveni, float $cena): string
    {
        return self::aktivitaQuery(
            self::ID_JINE_AKTIVITY,
            TypAktivity::EPIC,
            'Organizace Gameconu',
            $cena,
            $systemoveNastaveni,
        );
    }

    private static function aktivitaQuery(
        int                $idAktivity,
        int                $typAktivity,
        string             $nazev,
        float              $cena,
        SystemoveNastaveni $systemoveNastaveni,
    ): string
    {
        $rok = $systemoveNastaveni->rocnik();

        return <<<SQL
INSERT INTO akce_seznam
SET id_akce = $idAktivity,
    typ = $typAktivity,
    nazev_akce = '$nazev',
    rok = $rok,
    cena = $cena
SQL;
    }

    private static function uzivatelQuery(int $idUzivatele, string $jmeno, string $prijmeni): string
    {
        $login = RemoveDiacritics::toSnakeCaseId("$jmeno $prijmeni");
        $email = str_replace('_', '.', $login) . '@dot.com';
        return <<<SQL
INSERT INTO uzivatele_hodnoty SET id_uzivatele = $idUzivatele, login_uzivatele = '$login', jmeno_uzivatele = '$jmeno', prijmeni_uzivatele = '$prijmeni', email1_uzivatele = '$email'
SQL;
    }

    private static function prihlasenNaLetosniGcVcasQuery(int $idUzivatele): string
    {
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
        float              $upravaCeny = 0.0,
    ): string
    {
        $rok                          = $systemoveNastaveni->rocnik();
        $velkyDluh                    = $systemoveNastaveni->neplaticCastkaVelkyDluh();
        $poslalMalo                   = self::poslalMalo($systemoveNastaveni);
        $cena                         = $velkyDluh + $poslalMalo + $upravaCeny;
        $idPredmetuSCenouVelkehoDluhu = self::ID_NAHODNEHO_PREDMETU;

        return self::nakupQuery($idUzivatele, $idPredmetuSCenouVelkehoDluhu, $rok, $cena);
    }

    private static function nakupUbytovaniQuery(
        int                $idUzivatele,
        SystemoveNastaveni $systemoveNastaveni,
        float              $cena,
    ): string
    {
        $rok                 = $systemoveNastaveni->rocnik();
        $idPredmetuUbytovani = self::ID_PREDMETU_UBYTOVANI;

        return self::nakupQuery($idUzivatele, $idPredmetuUbytovani, $rok, $cena);
    }

    private static function nakupQuery(
        int   $idUzivatele,
        int   $idPredmetuUbytovani,
        int   $rok,
        float $cena,
    ): string
    {
        return <<<SQL
INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni)
VALUES ($idUzivatele, $idPredmetuUbytovani, $rok, $cena)
SQL;
    }

    private static function prihlaseniNaLarpQuery(int $idUzivatele): string
    {
        return self::prihlaseniNaAktivitu(self::ID_LARP_AKTIVITY, $idUzivatele);
    }

    private static function prihlaseniNaRpgQuery(int $idUzivatele): string
    {
        return self::prihlaseniNaAktivitu(self::ID_RPG_AKTIVITY, $idUzivatele);
    }

    private static function prihlaseniNaJinouAktivituQuery(int $idUzivatele): string
    {
        return self::prihlaseniNaAktivitu(self::ID_JINE_AKTIVITY, $idUzivatele);
    }

    private static function prihlaseniNaAktivitu(int $idAktivity, int $idUzivatele): string
    {
        $stavPrihlaseni = StavPrihlaseni::PRIHLASEN;
        return <<<SQL
INSERT INTO akce_prihlaseni
    SET id_akce = $idAktivity, id_uzivatele = $idUzivatele, id_stavu_prihlaseni = $stavPrihlaseni
SQL;

    }

    private static function poslalMaloQuery(int $idUzivatele, SystemoveNastaveni $systemoveNastaveni): string
    {
        $rok            = $systemoveNastaveni->rocnik();
        $poslalMalo     = self::poslalMalo($systemoveNastaveni);
        $uzivatelSystem = \Uzivatel::SYSTEM;

        return <<<SQL
INSERT INTO platby SET id_uzivatele = $idUzivatele, rok = {$rok}, castka = {$poslalMalo}, provedl = {$uzivatelSystem}
SQL;
    }

    private static function poslalMalo(SystemoveNastaveni $systemoveNastaveni): float
    {
        return (float)$systemoveNastaveni->neplaticCastkaPoslalDost() - 0.01;
    }

    private static function neodhlasovatQuery(int $idUzivatele): string
    {
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
    public function Muzu_zalogovat_notifikovani_neplaticu_o_brzkem_hromadnem_odhlaseni_a_zpetne_precist_kdy()
    {
        $hromadneOdhlaseniNeplaticu = new HromadneOdhlaseniNeplaticu(SystemoveNastaveni::vytvorZGlobals());

        $hromadneOdhlasovaniKdy = new \DateTimeImmutable('2023-05-01 01:01:01');
        $staloSeKdy             = new \DateTimeImmutable('2023-05-01 10:11:12');
        $poradiOznameni         = 1;
        $hromadneOdhlaseniNeplaticu->zalogujNotifikovaniNeplaticuOBrzkemHromadnemOdhlaseni(
            123,
            $hromadneOdhlasovaniKdy,
            $poradiOznameni,
            \Uzivatel::zIdUrcite(1),
            $staloSeKdy,
        );
        $kdyZLogu = $hromadneOdhlaseniNeplaticu->neplaticiNotifikovaniOBrzkemHromadnemOdhlaseniKdy(
            $hromadneOdhlasovaniKdy,
            $poradiOznameni,
        );
        self::assertEquals($staloSeKdy, $kdyZLogu);
    }

    /**
     * @test
     */
    public function Muzu_zalogovat_notifikovani_cfo_o_brzkem_hromadnem_odhlaseni_a_zpetne_precist_kdy()
    {
        $hromadneOdhlaseniNeplaticu = new HromadneOdhlaseniNeplaticu(SystemoveNastaveni::vytvorZGlobals());

        $hromadneOdhlasovaniKdy = new \DateTimeImmutable('2023-06-02 02:02:02');
        $staloSeKdy             = new \DateTimeImmutable('2023-06-02 03:04:05');
        $poradiOznameni         = 1;
        $hromadneOdhlaseniNeplaticu->zalogujNotifikovaniCfoOBrzkemHromadnemOdhlaseni(
            123,
            $hromadneOdhlasovaniKdy,
            $poradiOznameni,
            \Uzivatel::zIdUrcite(1),
            $staloSeKdy,
        );
        $kdyZLogu = $hromadneOdhlaseniNeplaticu->cfoNotifikovanOBrzkemHromadnemOdhlaseniKdy(
            $hromadneOdhlasovaniKdy,
            $poradiOznameni,
        );
        self::assertEquals($staloSeKdy, $kdyZLogu);
    }

    /**
     * @test
     */
    public function Nemuzu_ziskat_neplatice_kdyz_nejblizsi_odhlasovani_teprve_bude()
    {
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
    public function Nemuzu_ziskat_neplatice_kdyz_okno_pro_nejblizsi_odhlasovani_uz_bylo()
    {
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
    public function Nemuzu_ziskat_neplatice_kdyz_cas_pro_odhlasovani_je_zaroven_s_vlnou_aktivit()
    {
        $nejblizsiHromadneOdhlasovaniKdy = new \DateTimeImmutable();
        $nejblizsiVlnaOdhlaseni          = DateTimeGamecon::createFromInterface($nejblizsiHromadneOdhlasovaniKdy);
        $systemoveNastaveni              = $this->dejSystemoveNastaveniSNejblizsiVlnou($nejblizsiVlnaOdhlaseni);

        self::assertEquals(
            $nejblizsiHromadneOdhlasovaniKdy,
            $systemoveNastaveni->nejblizsiVlnaKdy(),
            'Pro tento negativní test potřebujeme datum odhlašování stejné jako nejbližší vlnu aktivit',
        );

        $hromadneOdhlaseniNeplaticu = new HromadneOdhlaseniNeplaticu($systemoveNastaveni);

        self::expectException(HromadneOdhlasovaniJePrilisBrzyPoVlne::class);

        $generator = $hromadneOdhlaseniNeplaticu->neplaticiAKategorie($nejblizsiHromadneOdhlasovaniKdy);
        $generator->next();
    }

    private function dejSystemoveNastaveniSNejblizsiVlnou(
        DateTimeGamecon         $nejblizsiVlnaKdy,
        DateTimeImmutableStrict $prvniHromadneOdhlasovani = null,
    ): SystemoveNastaveni
    {
        return new class($nejblizsiVlnaKdy, $prvniHromadneOdhlasovani) extends SystemoveNastaveni {
            public function __construct(
                private readonly DateTimeGamecon          $nejblizsiVlnaKdy,
                private readonly ?DateTimeImmutableStrict $nejblizsiHromadneOdhlasovaniKdy,
            )
            {
            }

            public function nejblizsiVlnaKdy(\DateTimeInterface $platnostZpetneKDatu = null, bool $overovatDatumZpetne = true): DateTimeGamecon
            {
                return $this->nejblizsiVlnaKdy;
            }

            public function ted(): DateTimeImmutableStrict
            {
                return new DateTimeImmutableStrict();
            }

            public function rocnik(): int
            {
                return ROCNIK;
            }

            public function prvniHromadneOdhlasovani(): DateTimeImmutableStrict
            {
                return $this->nejblizsiHromadneOdhlasovaniKdy ?? parent::prvniHromadneOdhlasovani();
            }

            public function jsmeNaBete(): bool
            {
                return false;
            }

            public function jsmeNaLocale(): bool
            {
                return false;
            }

        };
    }

    /**
     * Toto by se nemělo nidky stát. Ale známe ta "nikdy"...
     * @test
     */
    public function Nemuzu_ziskat_neplatice_kdyz_cas_pro_odhlasovani_je_az_po_vlne_aktivit()
    {
        $nejblizsiHromadneOdhlasovaniKdy = new \DateTimeImmutable();
        $nejblizsiVlnaOdhlasovani        = DateTimeGamecon::createFromInterface($nejblizsiHromadneOdhlasovaniKdy)
            ->modify('-1 second');
        $ted                             = new DateTimeImmutableStrict();
        $systemoveNastaveni              = $this->dejSystemoveNastaveniSNejblizsiVlnou($nejblizsiVlnaOdhlasovani);
        $platnostZpetneKDatu             = $ted->modify('-1 day');

        self::assertGreaterThan(
            $systemoveNastaveni->nejblizsiVlnaKdy($platnostZpetneKDatu),
            $nejblizsiHromadneOdhlasovaniKdy,
            'Pro tento negativní test potřebujeme datum odhlašování až po nejbližší vlně aktivit',
        );

        $hromadneOdhlaseniNeplaticu = new HromadneOdhlaseniNeplaticu($systemoveNastaveni);

        self::expectException(HromadneOdhlasovaniJePrilisBrzyPoVlne::class);
        $generator = $hromadneOdhlaseniNeplaticu->neplaticiAKategorie(
            $nejblizsiHromadneOdhlasovaniKdy,
            $platnostZpetneKDatu,
            $ted,
        );
        $generator->next();
    }

    /**
     * @test
     */
    public function Hromadne_odhlaseni_odhlasi_spravne_uzivatele_nebo_objednavky()
    {
        $nejblizsiHromadneOdhlasovaniKdy = new DateTimeImmutableStrict();
        $nejblizsiVlnaOdhlasovani        = DateTimeGamecon::createFromInterface($nejblizsiHromadneOdhlasovaniKdy)
            ->modify('+1 day');
        $ted                             = new DateTimeImmutableStrict();
        $systemoveNastaveni              = $this->dejSystemoveNastaveniSNejblizsiVlnou(
            $nejblizsiVlnaOdhlasovani,
            $nejblizsiHromadneOdhlasovaniKdy,
        );
        $platnostZpetneKDatu             ??= $ted->modify('-1 day');

        self::assertLessThan(
            $systemoveNastaveni->nejblizsiVlnaKdy($platnostZpetneKDatu),
            $nejblizsiHromadneOdhlasovaniKdy,
            'Pro tento test potřebujeme datum odhlašování před nejbližší vlnou aktivit',
        );

        $hromadneOdhlaseniNeplaticu        = new HromadneOdhlaseniNeplaticu($systemoveNastaveni);
        $neplaticiAKategoriePredOdhlasenim = $this->serazeniNeplaticiAKategorie(
            $nejblizsiHromadneOdhlasovaniKdy,
            $ted,
            $platnostZpetneKDatu,
            $hromadneOdhlaseniNeplaticu,
        );

        $ocekavaniNeplaticiPredOdhlasenim = [
            [
                'neplatic'            => self::VELKY_DLUH_NIC_NEDAM,
                'kategorie_neplatice' => KategorieNeplatice::LETOS_NEPOSLAL_NIC_A_LONI_NIC_NEBO_MA_VELKY_DLUH,
            ],
            [
                'neplatic'            => self::VELKY_DLUH_DAM_MALO,
                'kategorie_neplatice' => KategorieNeplatice::LETOS_POSLAL_MALO_A_MA_VELKY_DLUH,
            ],
            [
                'neplatic'            => self::VELKY_DLUH_DAM_MALO_ODHLASTE_UBYTOVANI,
                'kategorie_neplatice' => KategorieNeplatice::LETOS_POSLAL_MALO_A_MA_VELKY_DLUH,
            ],
            [
                'neplatic'            => self::VELKY_DLUH_DAM_MALO_ODHLASTE_AKTIVITY,
                'kategorie_neplatice' => KategorieNeplatice::LETOS_POSLAL_MALO_A_MA_VELKY_DLUH,
            ],
        ];
        self::assertSame($ocekavaniNeplaticiPredOdhlasenim, $neplaticiAKategoriePredOdhlasenim);

        foreach (self::LETOS_PRIHLASENI_UZIVATELE as $idUzivatele) {
            $testovaciUzivatelPoOdhlaseni = \Uzivatel::zIdUrcite($idUzivatele);
            self::assertTrue(
                $testovaciUzivatelPoOdhlaseni->gcPrihlasen(),
                "Uživatel '{$testovaciUzivatelPoOdhlaseni->jmeno()}' by měl být přihlášen",
            );
        }
        $velkyDluhDamMaloOdhlasteUbytovani = \Uzivatel::zIdUrcite(self::VELKY_DLUH_DAM_MALO_ODHLASTE_UBYTOVANI);
        $strukturovanyPrehled              = $velkyDluhDamMaloOdhlasteUbytovani->finance()->dejStrukturovanyPrehled();
        self::assertCount(
            2,
            $strukturovanyPrehled,
            "Uživatel '{$velkyDluhDamMaloOdhlasteUbytovani->jmeno()}' by měl mít dvě objednávky",
        );

        $velkyDluhDamMaloOdhlasteAktivity = \Uzivatel::zIdUrcite(self::VELKY_DLUH_DAM_MALO_ODHLASTE_AKTIVITY);
        $strukturovanyPrehled             = $velkyDluhDamMaloOdhlasteAktivity->finance()->dejStrukturovanyPrehled();
        self::assertCount(
            1,
            $strukturovanyPrehled,
            "Uživatel '{$velkyDluhDamMaloOdhlasteAktivity->jmeno()}' by měl mít před odhlášením objednaný jeden předmět",
        );
        $idPrihlasenychAktivit = $this->idckaPrihlasenychAktivit($velkyDluhDamMaloOdhlasteAktivity);
        self::assertSame(
            [self::ID_LARP_AKTIVITY, self::ID_RPG_AKTIVITY, self::ID_JINE_AKTIVITY],
            $idPrihlasenychAktivit,
            "Uživatel '{$velkyDluhDamMaloOdhlasteAktivity->jmeno()}' by měl mít před odhlášením přihlášené jiné aktivity",
        );

        $zdrojOdhlaseniZaklad = basename(__CLASS__, '.php');
        $zdrojOdhlaseni       = $zdrojOdhlaseniZaklad . '-1';

        $zruseneAktivityUzivatele = Aktivita::dejZruseneAktivityUzivatele(
            $velkyDluhDamMaloOdhlasteAktivity,
            $zdrojOdhlaseni,
            $systemoveNastaveni->rocnik(),
        );
        assertCount(0, $zruseneAktivityUzivatele, 'Před odhlášením nečekáme žádné už odhlášené aktivity');

        if (!defined('MAILY_DO_SOUBORU')) {
            // jistota je jistota
            define('MAILY_DO_SOUBORU', sys_get_temp_dir() . '/' . uniqid('test_maily_do_souboru.log'));
        }
        $zaznamnik = new Zaznamnik();
        $hromadneOdhlaseniNeplaticu->hromadneOdhlasit(
            $zdrojOdhlaseniZaklad,
            $zaznamnik,
            $platnostZpetneKDatu,
            $nejblizsiHromadneOdhlasovaniKdy,
        );

        $neplaticiAKategoriePoOdhlaseni = $this->serazeniNeplaticiAKategorie(
            $nejblizsiHromadneOdhlasovaniKdy,
            $ted,
            $platnostZpetneKDatu,
            $hromadneOdhlaseniNeplaticu,
        );
        self::assertSame([], $neplaticiAKategoriePoOdhlaseni, 'Po odhlášení by neměl zůstat žádný neplatič');

        $velkyDluhDamMaloOdhlasteUbytovani = \Uzivatel::zIdUrcite(self::VELKY_DLUH_DAM_MALO_ODHLASTE_UBYTOVANI);
        $strukturovanyPrehled              = $velkyDluhDamMaloOdhlasteUbytovani->finance()->dejStrukturovanyPrehled();
        self::assertCount(
            1,
            $strukturovanyPrehled,
            "Uživatel '{$velkyDluhDamMaloOdhlasteUbytovani->jmeno()}' by měl mít po odhlášení jednu objednávku",
        );

        $velkyDluhDamMaloOdhlasteAktivity = \Uzivatel::zIdUrcite(self::VELKY_DLUH_DAM_MALO_ODHLASTE_AKTIVITY);
        $strukturovanyPrehled             = $velkyDluhDamMaloOdhlasteAktivity->finance()->dejStrukturovanyPrehled();
        self::assertCount(
            1,
            $strukturovanyPrehled,
            "Uživatel '{$velkyDluhDamMaloOdhlasteAktivity->jmeno()}' by měl mít po odhlášení jiný počet objednaných předmětů",
        );
        $idPrihlasenychAktivit = $this->idckaPrihlasenychAktivit($velkyDluhDamMaloOdhlasteAktivity);
        self::assertSame(
            [self::ID_JINE_AKTIVITY],
            $idPrihlasenychAktivit,
            "Uživatel '{$velkyDluhDamMaloOdhlasteAktivity->jmeno()}' by měl mít po odhlášení přihlášené jiné aktivity",
        );

        foreach (self::LETOS_PRIHLASENI_UZIVATELE as $idUzivatele) {
            $testovaciUzivatelPoOdhlaseni = \Uzivatel::zIdUrcite($idUzivatele);
            if (in_array($idUzivatele, [self::VELKY_DLUH_NIC_NEDAM, self::VELKY_DLUH_DAM_MALO], true)) {
                self::assertFalse(
                    $testovaciUzivatelPoOdhlaseni->gcPrihlasen(),
                    "Uživatel '{$testovaciUzivatelPoOdhlaseni->jmeno()}' by měl být odhlášen",
                );
            } else {
                self::assertTrue(
                    $testovaciUzivatelPoOdhlaseni->gcPrihlasen(),
                    "Uživatel '{$testovaciUzivatelPoOdhlaseni->jmeno()}' by měl zůstat přihlášen",
                );
            }
        }

        $idckaZaznamenanychOdhlasenych = array_map(static fn(\Uzivatel $uzivatel) => $uzivatel->id(), $zaznamnik->entity());
        sort($idckaZaznamenanychOdhlasenych);
        self::assertSame(
            [self::VELKY_DLUH_NIC_NEDAM, self::VELKY_DLUH_DAM_MALO],
            $idckaZaznamenanychOdhlasenych,
            'Očekáván jiný seznam zaznamenaných odhlášených uživatelů',
        );

        $zruseneAktivityUzivatele = Aktivita::dejZruseneAktivityUzivatele(
            $velkyDluhDamMaloOdhlasteAktivity,
            $zdrojOdhlaseni,
            $systemoveNastaveni->rocnik(),
        );
        assertCount(
            2,
            $zruseneAktivityUzivatele,
            "Po odhlášení Uživatele '{$testovaciUzivatelPoOdhlaseni->jmeno()}' čekáme jiný počet odhlášených aktivit",
        );
        $idckaZrusenychAktivitUzivatele = array_map(
            static fn(Aktivita $aktivita) => $aktivita->id(),
            $zruseneAktivityUzivatele,
        );
        sort($idckaZrusenychAktivitUzivatele);
        self::assertSame(
            [
                self::ID_LARP_AKTIVITY,
                self::ID_RPG_AKTIVITY,
            ],
            $idckaZrusenychAktivitUzivatele,
            "Očeáváme jiné odhlášené aktivity u uživatele '{$testovaciUzivatelPoOdhlaseni->jmeno()}'",
        );
    }

    private function serazeniNeplaticiAKategorie(
        \DateTimeInterface         $nejblizsiHromadneOdhlasovaniKdy,
        \DateTimeInterface         $ted,
        \DateTimeInterface         $platnostZpetneKDatu,
        HromadneOdhlaseniNeplaticu $hromadneOdhlaseniNeplaticu,
    ): array
    {
        $generator                 = $hromadneOdhlaseniNeplaticu->neplaticiAKategorie(
            $nejblizsiHromadneOdhlasovaniKdy,
            $platnostZpetneKDatu,
            $ted,
        );
        $neplaticiAKategorieScalar = [];
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
            static fn(array $nejakyZaznam, array $jinyZanam) => $nejakyZaznam['neplatic'] <=> $jinyZanam['neplatic'],
        );

        return $neplaticiAKategorieScalar;
    }

    /**
     * @param \Uzivatel $uzivatel
     * @return int[]
     */
    private function idckaPrihlasenychAktivit(\Uzivatel $uzivatel): array
    {
        $idckaPrihlasenychAktivit = array_map(
            static fn(Aktivita $aktivita) => $aktivita->id(),
            $uzivatel->aktivityRyzePrihlasene(),
        );
        sort($idckaPrihlasenychAktivit);

        return $idckaPrihlasenychAktivit;
    }

}
