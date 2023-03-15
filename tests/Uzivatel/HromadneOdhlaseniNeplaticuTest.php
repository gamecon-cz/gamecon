<?php

namespace Gamecon\Tests\Uzivatel;

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
}
