<?php

declare(strict_types=1);

namespace App\Tests\Discount;

use App\Discount\DiscountableItem;
use App\Discount\DiscountCalculation;
use App\Discount\DiscountRule;
use App\Enum\ProductTagCode;
use PHPUnit\Framework\TestCase;

/**
 * Cenový žebřík — kolikátý kus stojí kolik.
 *
 * Frontend ho dostane celý dopředu, takže po přidání do košíku ukáže cenu dalšího kusu
 * bez dotazu na server. Musí proto sedět s tím, co by motor spočítal položku po položce.
 */
class PriceStepsTest extends TestCase
{
    private function pravidlo(string $code, string $name, int $pravo, string $parameters): DiscountRule
    {
        return DiscountRule::fromRow([
            'code'           => $code,
            'name'           => $name,
            'required_right' => $pravo,
            'parameters'     => $parameters,
        ]);
    }

    private function tricko(float $cena = 400.0): DiscountableItem
    {
        return new DiscountableItem(
            key: 'tricko',
            productCode: 'tricko_ucastnicke',
            price: $cena,
            tags: [ProductTagCode::TRICKO],
        );
    }

    /**
     * @param DiscountRule[] $pravidla
     * @param int[]          $prava
     */
    private function vypocet(array $pravidla, array $prava): DiscountCalculation
    {
        return new DiscountCalculation($pravidla, $prava, [], 0.0);
    }

    public function testBezNarokuJedinyStupenZaPlnouCenu(): void
    {
        $steps = $this->vypocet([], [])->priceSteps($this->tricko());

        self::assertCount(1, $steps);
        self::assertSame(1, $steps[0]->fromQuantity);
        self::assertSame(400.0, $steps[0]->price);
        self::assertNull($steps[0]->ruleCode);
    }

    public function testJedenKusZdarmaPakPlnaCena(): void
    {
        $steps = $this->vypocet([
            $this->pravidlo('jedno_tricko_zdarma', 'Jedno tričko zdarma', 1035, '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":1}'),
        ], [1035])->priceSteps($this->tricko());

        self::assertCount(2, $steps);
        self::assertSame([1, 0.0, 'jedno_tricko_zdarma'], [$steps[0]->fromQuantity, $steps[0]->price, $steps[0]->ruleCode]);
        self::assertSame([2, 400.0, null], [$steps[1]->fromQuantity, $steps[1]->price, $steps[1]->ruleCode]);
    }

    /**
     * Dva nároky se řetězí: dvě trička z jednoho pravidla, třetí z druhého, čtvrté za své.
     */
    public function testNarokySeReteziPodlePriority(): void
    {
        $steps = $this->vypocet([
            $this->pravidlo('dve_tricka_zdarma', 'Dvě trička zdarma', 1020, '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":2}'),
            $this->pravidlo('jedno_tricko_zdarma', 'Jedno tričko zdarma', 1035, '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":1}'),
        ], [1020, 1035])->priceSteps($this->tricko());

        self::assertCount(3, $steps);
        self::assertSame([1, 'dve_tricka_zdarma'], [$steps[0]->fromQuantity, $steps[0]->ruleCode]);
        self::assertSame([3, 'jedno_tricko_zdarma'], [$steps[1]->fromQuantity, $steps[1]->ruleCode]);
        self::assertSame([4, 400.0, null], [$steps[2]->fromQuantity, $steps[2]->price, $steps[2]->ruleCode]);
    }

    /**
     * Sleva bez omezeného počtu platí pořád, takže žebřík má jediný stupeň.
     */
    public function testNeomezenaSlevaJeJedinyStupen(): void
    {
        $steps = $this->vypocet([
            $this->pravidlo('sleva', 'Sleva', 1004, '{"scope":"tag","effect":"percent","tag":"tricko","percent":25}'),
        ], [1004])->priceSteps($this->tricko());

        self::assertCount(1, $steps);
        self::assertSame(300.0, $steps[0]->price);
        self::assertSame(100.0, $steps[0]->discountAmount);
    }

    /**
     * Co už zákazník má, nárok spotřebovalo — žebřík začíná tam, kde reálně stojí.
     */
    public function testJizKoupeneKusySpotrebujiNarok(): void
    {
        $pravidla = [
            $this->pravidlo('dve_tricka_zdarma', 'Dvě trička zdarma', 1020, '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":2}'),
        ];

        $steps = $this->vypocet($pravidla, [1020])->priceSteps($this->tricko(), jizKoupeno: 1);

        self::assertSame(0.0, $steps[0]->price, 'Druhý kus má být pořád zdarma');
        self::assertSame(400.0, $steps[1]->price, 'Třetí kus už za plnou cenu');
    }

    /**
     * Žebřík je jen jiný pohled na týž výpočet — kdyby se rozešel s tím, co motor reálně
     * naúčtuje, ukazoval by frontend jinou cenu, než jaká se zapíše.
     */
    public function testZebrikSediSTimCoMotorNauctuje(): void
    {
        $pravidla = [
            $this->pravidlo('dve_tricka_zdarma', 'Dvě trička zdarma', 1020, '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":2}'),
            $this->pravidlo('jedno_tricko_zdarma', 'Jedno tričko zdarma', 1035, '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":1}'),
        ];

        $kusu = 5;
        $polozky = [];
        for ($i = 0; $i < $kusu; ++$i) {
            $polozky[] = new DiscountableItem(
                key: $i,
                productCode: 'tricko_ucastnicke',
                price: 400.0,
                tags: [ProductTagCode::TRICKO],
            );
        }
        $slevy = $this->vypocet($pravidla, [1020, 1035])->apply($polozky);

        $zebrik = $this->vypocet($pravidla, [1020, 1035])->priceSteps($this->tricko());

        foreach (range(1, $kusu) as $poradi) {
            $zApply = isset($slevy[$poradi - 1]) ? $slevy[$poradi - 1]->finalPrice : 400.0;
            $zeZebriku = $this->cenaZeZebriku($zebrik, $poradi);
            self::assertSame($zApply, $zeZebriku, sprintf('%d. kus', $poradi));
        }
    }

    /**
     * @param \App\Discount\PriceStep[] $zebrik
     */
    private function cenaZeZebriku(array $zebrik, int $poradi): float
    {
        $cena = $zebrik[0]->price;
        foreach ($zebrik as $stupen) {
            if ($stupen->fromQuantity <= $poradi) {
                $cena = $stupen->price;
            }
        }

        return $cena;
    }

    /**
     * Nárok „nejlevnější tričko zdarma" dostane v košíku ta nejlevnější položka. Žebřík
     * ale počítá jeden produkt bez košíku, takže by nulu slíbil i u dražšího trička —
     * a to by se nakonec zaplatilo. Proto se takové pravidlo do žebříku nedostane.
     */
    public function testNejlevnejsiZdarmaSeDoZebrikuNedostane(): void
    {
        $pravidlo = $this->pravidlo(
            'tricko_za_bonus',
            'Tričko za bonus',
            1012,
            '{"scope":"tag_cheapest","effect":"free","tag":"tricko","maxQuantity":1}',
        );

        $steps = $this->vypocet([$pravidlo], [1012])->priceSteps($this->tricko(600.0));

        self::assertCount(1, $steps);
        self::assertSame(600.0, $steps[0]->price, 'Dražší tričko nesmí hlásit nulu');
        self::assertNull($steps[0]->ruleCode);
    }

    /**
     * Strop v cyklu počítá průchody, ne stupně — a už koupené kusy je taky spotřebují.
     * U velkého nároku a hodně koupených kusů cyklus doběhl dřív, než stihl cokoli
     * vydat, a fallback pak tvrdil „plná cena", přestože nárok pořád zbýval.
     */
    public function testVelkyNarokSMnohaKoupenymiPoradNabizeSlevu(): void
    {
        $steps = $this->vypocet([
            $this->pravidlo('sto_zdarma', 'Sto zdarma', 1035, '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":100}'),
        ], [1035])->priceSteps($this->tricko(), jizKoupeno: 60);

        self::assertSame(0.0, $steps[0]->price, '61. kus má být pořád zdarma — nárok je 100');
        self::assertSame('sto_zdarma', $steps[0]->ruleCode);
    }

    /**
     * Pravidlo, které nakonec nic neubere (nastavená částka 0), není sleva. apply() ho
     * zahodí, žebřík ho hlásil jako stupeň — a u produktu za nulu vyrobil „0 Kč / 0 Kč".
     */
    public function testPravidloSNulovouSlevouNeniStupen(): void
    {
        $steps = $this->vypocet([
            $this->pravidlo('nic_neubere', 'Nic neubere', 1035, '{"scope":"tag","effect":"fixed_amount","tag":"tricko","amount":0}'),
        ], [1035])->priceSteps($this->tricko());

        self::assertCount(1, $steps);
        self::assertNull($steps[0]->ruleCode, 'Nulová sleva se nemá tvářit jako nárok');
        self::assertSame(400.0, $steps[0]->price);
    }

    /**
     * Produkt zdarma pro všechny: „zdarma" na něm nic neubere, takže žebřík má mít
     * jediný stupeň, ne dva stejné.
     */
    public function testProduktZaNuluMaJedinyStupen(): void
    {
        $steps = $this->vypocet([
            $this->pravidlo('jedno_tricko_zdarma', 'Jedno tričko zdarma', 1035, '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":1}'),
        ], [1035])->priceSteps($this->tricko(0.0));

        self::assertCount(1, $steps);
        self::assertSame(0.0, $steps[0]->price);
        self::assertNull($steps[0]->ruleCode);
    }

    /**
     * Omezený nárok vyčerpá a dál platí neomezená sleva. Neomezené pravidlo žebřík
     * ukončuje, takže tohle je tvar, kde by se ukončení nejspíš rozešlo s účtováním.
     */
    public function testOmezenyNarokPakNeomezenaSlevaSediSMotorem(): void
    {
        $pravidla = [
            $this->pravidlo('jedno_tricko_zdarma', 'Jedno tričko zdarma', 1035, '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":1}'),
            $this->pravidlo('ctvrtina', 'Čtvrtina dolů', 1004, '{"scope":"tag","effect":"percent","tag":"tricko","percent":25}'),
        ];
        $vypocet = $this->vypocet($pravidla, [1035, 1004]);

        $polozky = [];
        for ($i = 0; $i < 4; ++$i) {
            $polozky[] = new DiscountableItem(
                key: $i,
                productCode: 'tricko_ucastnicke',
                price: 400.0,
                tags: [ProductTagCode::TRICKO],
            );
        }

        $slevy = $vypocet->apply($polozky);
        $zebrik = $vypocet->priceSteps($this->tricko());

        foreach (range(1, 4) as $poradi) {
            self::assertSame(
                isset($slevy[$poradi - 1]) ? $slevy[$poradi - 1]->finalPrice : 400.0,
                $this->cenaZeZebriku($zebrik, $poradi),
                sprintf('%d. kus', $poradi),
            );
        }
    }

    public function testVsechnyNarokyVycerpaneZbydePlnaCena(): void
    {
        $steps = $this->vypocet([
            $this->pravidlo('jedno_tricko_zdarma', 'Jedno tričko zdarma', 1035, '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":1}'),
        ], [1035])->priceSteps($this->tricko(), jizKoupeno: 1);

        self::assertCount(1, $steps);
        self::assertSame(400.0, $steps[0]->price);
        self::assertNull($steps[0]->ruleCode);
    }
}
