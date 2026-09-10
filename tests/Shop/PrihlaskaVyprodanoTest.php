<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use Gamecon\Shop\ShopUbytovani;
use Gamecon\Shop\StavPredmetu;

/**
 * Vyprodané a už neprodejné položky: ověřuje, že je přihláška odmítne
 * i tehdy, když si účastník poskládá POST ručně mimo formulář.
 */
class PrihlaskaVyprodanoTest extends AbstractTestPrihlaska
{
    /**
     * @test
     */
    public function vyprodanyPredmetNejdeKoupit(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $jinyUcastnik = $this->vytvorBeznehoUzivatele();

        $idPredmetu = $this->vytvorPredmet('Poslední placka', kusuVyrobeno: 1);
        $this->odesliPrihlasku($jinyUcastnik, [
            'shopP' => [
                $idPredmetu => 1,
            ],
        ]);
        self::assertSame(0, $this->zbyvajiciKusy($idPredmetu), 'Předmět musí být vyprodaný');

        $chyba = $this->odesliPrihlaskuAZachytChybu($uzivatel, [
            'shopP' => [
                $idPredmetu => 1,
            ],
        ]);

        self::assertNotNull($chyba, 'Nákup vyprodaného předmětu musí skončit chybou');
        self::assertStringContainsString('Zbývá dostupných kusů: 0', $chyba->getMessage());
        self::assertSame(0, $this->pocetNakupu($uzivatel, $idPredmetu), 'Vyprodaný předmět se nesmí uložit');
    }

    /**
     * @test
     */
    public function nakupPresZbyvajiciZasobuNeprojdeAniZCasti(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $idPredmetu = $this->vytvorPredmet('Skoro vyprodaná placka', kusuVyrobeno: 2);

        $chyba = $this->odesliPrihlaskuAZachytChybu($uzivatel, [
            'shopP' => [
                $idPredmetu => 3,
            ],
        ]);

        self::assertNotNull($chyba, 'Nákup přes zásobu musí skončit chybou');
        self::assertStringContainsString('Zbývá dostupných kusů: 2', $chyba->getMessage());
        self::assertSame(
            0,
            $this->pocetNakupu($uzivatel, $idPredmetu),
            'Nesmí se uložit ani ty kusy, které by se do zásoby vešly',
        );
    }

    /**
     * @test
     */
    public function vyprodaneUbytovaniNejdeObjednat(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $jinyUcastnik = $this->vytvorBeznehoUzivatele();

        $idsNoci = $this->vytvorUbytovaniNaDvouNocich(kusuVyrobeno: 1);
        $this->odesliPrihlasku($jinyUcastnik, [
            'shopUbytovaniDny' => $idsNoci,
        ]);

        $chyba = $this->odesliPrihlaskuAZachytChybu($uzivatel, [
            'shopUbytovaniDny' => $idsNoci,
        ]);

        self::assertNotNull($chyba, 'Objednávka zabraného ubytování musí skončit chybou');
        self::assertStringContainsString('zabrané', $chyba->getMessage());
        self::assertSame(0, $this->pocetVsechNakupu($uzivatel), 'Zabrané ubytování se nesmí uložit');
    }

    /**
     * @test
     */
    public function nabidkaNeobsahujePozastavenyPredmet(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $idPredmetu = $this->vytvorPredmet('Stažená placka', stav: StavPredmetu::POZASTAVENY);

        self::assertFalse(
            $this->jeNabizenKProdeji($uzivatel, $idPredmetu),
            'Pozastavený předmět se nesmí nabízet k prodeji',
        );
    }

    /**
     * @test
     */
    public function nabidkaNeobsahujePredmetPoUplynutiNabizetDo(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $idPredmetu = $this->vytvorPredmet('Prošlá placka', nabizetDo: '2000-01-01 00:00:00');

        self::assertFalse(
            $this->jeNabizenKProdeji($uzivatel, $idPredmetu),
            'Předmět po uplynutí nabizet_do se nesmí nabízet k prodeji',
        );
    }

    /**
     * @test
     */
    public function nabidkaObsahujeBeznyPredmet(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $idPredmetu = $this->vytvorPredmet('Placka');

        self::assertTrue(
            $this->jeNabizenKProdeji($uzivatel, $idPredmetu),
            'Veřejný předmět letošního ročníku se musí nabízet — jinak testy nabídky nic neměří',
        );
    }

    /**
     * Pozastavený předmět zmizí z nabídky, ale zpracování formuláře ho stále
     * přijme — whitelist v Shop::zpracujPredmety() staví na všech předmětech
     * ročníku, ne na těch skutečně nabízených, a Shop::prodat() hlídá jen
     * zásobu a ročník. Ručně poskládaný POST tak koupí staženou položku.
     *
     * @test
     */
    public function pozastavenyPredmetJdeKoupitRucnePoskladanymPostem(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $idPredmetu = $this->vytvorPredmet('Stažená placka', stav: StavPredmetu::POZASTAVENY);

        $chyba = $this->odesliPrihlaskuAZachytChybu($uzivatel, [
            'shopP' => [
                $idPredmetu => 1,
            ],
        ]);

        self::assertNull($chyba, 'Zatím se nekontroluje, chování je tu jen zafixované');
        self::assertSame(
            1,
            $this->pocetNakupu($uzivatel, $idPredmetu),
            'Pozastavený předmět se dnes koupit dá — až to někdo opraví, test musí spadnout',
        );
    }

    /**
     * @test
     */
    public function predmetZJinehoRocnikuNejdeKoupit(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $idPredmetu = $this->vytvorPredmet('Loňská placka', modelRok: ROCNIK - 1);

        $chyba = $this->odesliPrihlaskuAZachytChybu($uzivatel, [
            'shopP' => [
                $idPredmetu => 1,
            ],
        ]);

        self::assertSame(
            0,
            $this->pocetNakupu($uzivatel, $idPredmetu),
            'Předmět z jiného ročníku se nesmí uložit',
        );
        self::assertNull($chyba, 'Předmět mimo ročník se z formuláře tiše zahodí, není to chyba účastníka');
    }

    /**
     * @test
     */
    public function ubytovaniZJinehoRocnikuNejdeObjednat(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $idsNoci = $this->vytvorUbytovaniNaDvouNocich();
        $idCiziNoci = $this->vytvorUbytovani('Loňské ubytování', self::DEN_CTVRTEK, modelRok: ROCNIK - 1);

        $chyba = $this->odesliPrihlaskuAZachytChybu($uzivatel, [
            'shopUbytovaniDny' => [...$idsNoci, $idCiziNoci],
        ]);

        self::assertNotNull($chyba, 'Ubytování z jiného ročníku musí skončit chybou');
        self::assertStringContainsString('není dostupná pro ročník', $chyba->getMessage());
        self::assertSame(0, $this->pocetVsechNakupu($uzivatel), 'Nesmí se uložit ani platné noci ze stejného POSTu');
    }

    /**
     * @test
     */
    public function ubytovaniNaJedinouNocNejdeObjednat(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $idNoci = $this->vytvorUbytovani('Ubytování čtvrtek', self::DEN_CTVRTEK);

        $chyba = $this->odesliPrihlaskuAZachytChybu($uzivatel, [
            'shopUbytovaniDny' => [$idNoci],
        ]);

        self::assertNotNull($chyba, 'Jediná noc bez zvláštního práva musí skončit chybou');
        self::assertSame(ShopUbytovani::CHYBA_MINIMALNE_DVE_NOCI, $chyba->getMessage());
        self::assertSame(0, $this->pocetVsechNakupu($uzivatel));
    }

    /**
     * @test
     */
    public function nenavazujiciNociUbytovaniNejdouObjednat(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $idCtvrtek = $this->vytvorUbytovani('Ubytování čtvrtek', self::DEN_CTVRTEK);
        $idSobota = $this->vytvorUbytovani('Ubytování sobota', self::DEN_SOBOTA);

        $chyba = $this->odesliPrihlaskuAZachytChybu($uzivatel, [
            'shopUbytovaniDny' => [$idCtvrtek, $idSobota],
        ]);

        self::assertNotNull($chyba, 'Nenavazující noci musí skončit chybou');
        self::assertSame(ShopUbytovani::CHYBA_NAVAZUJICI_NOCI, $chyba->getMessage());
        self::assertSame(0, $this->pocetVsechNakupu($uzivatel));
    }

    /**
     * Neúspěšná položka musí vzít s sebou celou přihlášku — účastník nesmí
     * skončit s ubytováním zaplaceným a předmětem neuloženým.
     *
     * @test
     */
    public function chybaJednePolozkyZrusiCelouObjednavku(): void
    {
        $uzivatel = $this->vytvorBeznehoUzivatele();
        $jinyUcastnik = $this->vytvorBeznehoUzivatele();

        $idVyprodaneho = $this->vytvorPredmet('Poslední placka', kusuVyrobeno: 1);
        $this->odesliPrihlasku($jinyUcastnik, [
            'shopP' => [
                $idVyprodaneho => 1,
            ],
        ]);

        $idsNoci = $this->vytvorUbytovaniNaDvouNocich();

        $chyba = $this->odesliPrihlaskuAZachytChybu($uzivatel, [
            'shopP' => [
                $idVyprodaneho => 1,
            ],
            'shopUbytovaniDny' => $idsNoci,
        ]);

        self::assertNotNull($chyba);
        self::assertSame(
            0,
            $this->pocetVsechNakupu($uzivatel),
            'Ubytování se nesmí uložit, když ve stejné přihlášce selhal předmět',
        );
    }
}
