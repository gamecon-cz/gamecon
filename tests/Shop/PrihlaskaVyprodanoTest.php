<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use Gamecon\Shop\ShopUbytovani;

/**
 * Ubytování, které přihláška musí odmítnout: vyprodané, z cizího ročníku, jediná noc
 * i nenavazující noci — a to i když si účastník poskládá POST ručně mimo formulář.
 *
 * Merch tu není: kupuje se přes API košíku, viz App\Tests\Service\CartServiceStockTest.
 */
class PrihlaskaVyprodanoTest extends AbstractTestPrihlaska
{
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

        // Jídlo se zpracovává až po ubytování, takže vyprodané jídlo shodí
        // přihlášku ve chvíli, kdy jsou noci ubytování už zapsané. Vyprodaný
        // předmět by se sem nehodil — ten padá dřív, než se stihne uložit cokoli.
        $idVyprodanehoJidla = $this->vytvorJidlo('oběd', den: self::DEN_PATEK, kusuVyrobeno: 1);
        $this->odesliPrihlasku($jinyUcastnik, [
            'cShopJidloZmen' => '1',
            'cShopJidlo'     => [
                $idVyprodanehoJidla => '1',
            ],
        ]);

        $idsNoci = $this->vytvorUbytovaniNaDvouNocich();

        $chyba = $this->odesliPrihlaskuAZachytChybu($uzivatel, [
            'shopUbytovaniDny' => $idsNoci,
            'cShopJidloZmen'   => '1',
            'cShopJidlo'       => [
                $idVyprodanehoJidla => '1',
            ],
        ]);

        self::assertNotNull($chyba);
        self::assertSame(
            0,
            $this->pocetVsechNakupu($uzivatel),
            'Ubytování se nesmí uložit, když v téže přihlášce selhalo později zpracované jídlo',
        );
    }
}
