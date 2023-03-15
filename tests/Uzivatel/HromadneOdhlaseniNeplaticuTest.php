<?php

namespace Gamecon\Tests\Uzivatel;

use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\DbTest;
use Gamecon\Uzivatel\Exceptions\NaHromadneOdhlasovaniJeBrzy;
use Gamecon\Uzivatel\Exceptions\NaHromadneOdhlasovaniJePozde;
use Gamecon\Uzivatel\HromadneOdhlaseniNeplaticu;

class HromadneOdhlaseniNeplaticuTest extends DbTest
{

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
        $nejblizsiHromadneOdhlasovani = new \DateTimeImmutable();
        $systemoveNastaveni           = $this->dejSystemoveNastaveniSNejblizsiVlnou(
            DateTimeGamecon::createFromInterface($nejblizsiHromadneOdhlasovani)
        );
        $hromadneOdhlaseniNeplaticu   = new HromadneOdhlaseniNeplaticu($systemoveNastaveni);

        self::expectException(NaHromadneOdhlasovaniJePozde::class);
        self::assertEquals($nejblizsiHromadneOdhlasovani, $systemoveNastaveni->nejblizsiVlnaKdy());
        $generator = $hromadneOdhlaseniNeplaticu->neplaticiAKategorie($nejblizsiHromadneOdhlasovani);
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
        };
    }

    /**
     * Toto by se nemělo nidky stát. Ale známe ta "nikdy"...
     * @test
     */
    public function Nemuzu_ziskat_neplatice_kdyz_cas_pro_odhlasovani_je_az_po_vlne_aktivit() {
        $nejblizsiHromadneOdhlasovani = new \DateTimeImmutable();
        $nejblizsiVlnaOdhlasovani     = DateTimeGamecon::createFromInterface($nejblizsiHromadneOdhlasovani)
            ->modify('-1 second');
        $ted                          = new DateTimeImmutableStrict();
        $systemoveNastaveni           = $this->dejSystemoveNastaveniSNejblizsiVlnou($nejblizsiVlnaOdhlasovani);
        $platnostZpetneKDatu          ??= $ted->modify('-1 day');
        $hromadneOdhlaseniNeplaticu   = new HromadneOdhlaseniNeplaticu($systemoveNastaveni);

        self::expectException(NaHromadneOdhlasovaniJePozde::class);
        self::assertGreaterThan($systemoveNastaveni->nejblizsiVlnaKdy($platnostZpetneKDatu), $nejblizsiHromadneOdhlasovani);
        $generator = $hromadneOdhlaseniNeplaticu->neplaticiAKategorie(
            $nejblizsiHromadneOdhlasovani,
            $ted,
            $platnostZpetneKDatu
        );
        $generator->next();
    }

}
