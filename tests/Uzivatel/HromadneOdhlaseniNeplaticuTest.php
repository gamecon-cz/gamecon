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
    private const ID_PREDMETU_S_CENOU_VELKEHO_DLUHU = 111;

    protected static bool $disableStrictTransTables = true;

    protected static function getInitQueries(): array {
        $systemoveNastaveni = SystemoveNastaveni::vytvorZGlobals();

        $queries[] = self::predmetSCenouVelkehoDluhuQuery($systemoveNastaveni);

        $queries[] = self::uzivatelQuery(222, 'Velký dluh', 'Nic nedám');
        $queries[] = self::prihlasenNaLetosniGcVcasQuery(222);
        $queries[] = self::velkyDluhQuery(222, $systemoveNastaveni);

        $queries[] = self::uzivatelQuery(223, 'Velký dluh', 'Dám málo');
        $queries[] = self::prihlasenNaLetosniGcVcasQuery(223);
        $queries[] = self::velkyDluhQuery(223, $systemoveNastaveni);
        $queries[] = self::poslalMaloQuery(223, $systemoveNastaveni);

        $queries[] = self::uzivatelQuery(2220, 'Velký dluh', 'Nic nedám, Neodhlašovat');
        $queries[] = self::prihlasenNaLetosniGcVcasQuery(2220);
        $queries[] = self::velkyDluhQuery(2220, $systemoveNastaveni);
        $queries[] = self::neodhlasovatQuery(2220);

        $queries[] = self::uzivatelQuery(2230, 'Velký dluh', 'Dám málo, Neodhlašovat');
        $queries[] = self::prihlasenNaLetosniGcVcasQuery(2230);
        $queries[] = self::velkyDluhQuery(2230, $systemoveNastaveni);
        $queries[] = self::poslalMaloQuery(2230, $systemoveNastaveni);
        $queries[] = self::neodhlasovatQuery(2230);

        $queries[] = self::uzivatelQuery(2221, 'Velký dluh', 'Nic nedám, Letos nejsem');
        $queries[] = self::velkyDluhQuery(2221, $systemoveNastaveni);

        $queries[] = self::uzivatelQuery(2231, 'Velký dluh', 'Dám málo, Letos nejsem');
        $queries[] = self::velkyDluhQuery(2231, $systemoveNastaveni);
        $queries[] = self::poslalMaloQuery(2231, $systemoveNastaveni);

        return $queries;
    }

    private static function predmetSCenouVelkehoDluhuQuery(SystemoveNastaveni $systemoveNastaveni): string {
        $idPredmetuSCenouVelkehoDluhu = self::ID_PREDMETU_S_CENOU_VELKEHO_DLUHU;
        $typVstupne                   = TypPredmetu::VSTUPNE;
        $rok                          = $systemoveNastaveni->rocnik();
        $velkyDluh                    = $systemoveNastaveni->neplaticCastkaVelkyDluh();

        return <<<SQL
INSERT INTO shop_predmety
SET id_predmetu = $idPredmetuSCenouVelkehoDluhu,
    nazev = 'Předražené vstupné',
    typ = $typVstupne,
    model_rok = $rok,
    cena_aktualni = $velkyDluh
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

    private static function velkyDluhQuery(int $idUzivatele, SystemoveNastaveni $systemoveNastaveni): string {
        $rok                          = $systemoveNastaveni->rocnik();
        $velkyDluh                    = $systemoveNastaveni->neplaticCastkaVelkyDluh();
        $poslalMalo                   = self::poslalMalo($systemoveNastaveni);
        $cena                         = $velkyDluh + $poslalMalo;
        $idPredmetuSCenouVelkehoDluhu = self::ID_PREDMETU_S_CENOU_VELKEHO_DLUHU;

        return <<<SQL
INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni)
VALUES ($idUzivatele, $idPredmetuSCenouVelkehoDluhu, $rok, $cena)
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
        return (float)$systemoveNastaveni->neplaticCastkaPoslalDost() - 1;
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
                'kategorie_neplatice' => $kategorieNeplatice->dejCiselnouKategoriiNeplatice(),
            ];
        }
        usort(
            $neplaticiAKategorieScalar,
            static fn(array $nejakyZaznam, array $jinyZanam) => $nejakyZaznam['neplatic'] <=> $jinyZanam['neplatic']
        );
        self::assertSame(
            [
                [
                    'neplatic'            => 222,
                    'kategorie_neplatice' => KategorieNeplatice::LETOS_NEPOSLAL_NIC_A_LONI_NIC_NEBO_MA_VELKY_DLUH,
                ],
                [
                    'neplatic'            => 223,
                    'kategorie_neplatice' => KategorieNeplatice::LETOS_POSLAL_MALO_A_MA_VELKY_DLUH,
                ],
            ],
            $neplaticiAKategorieScalar
        );
    }

}
