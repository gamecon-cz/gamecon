<?php

declare(strict_types=1);

namespace App\Tests\Discount;

use App\Discount\DiscountableItem;
use App\Discount\DiscountCalculation;
use App\Discount\DiscountRule;
use App\Discount\SpentQuota;
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

    private function kostka(string $kod, float $cena): DiscountableItem
    {
        return new DiscountableItem(key: $kod, productCode: $kod, price: $cena, tags: []);
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

        $steps = $this->vypocet($pravidla, [1020])->priceSteps($this->tricko(), alreadyBought: 1);

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
        ], [1035])->priceSteps($this->tricko(), alreadyBought: 60);

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

    /**
     * Popis zvýhodnění skládá backend, ne frontend — až se hlášky budou překládat, je to
     * jedno místo místo rozsypané češtiny v TypeScriptu.
     */
    public function testStupenNeseCeskyPopisZvyhodneni(): void
    {
        $steps = $this->vypocet([
            $this->pravidlo('jedno_tricko_zdarma', 'Jedno tričko zdarma', 1035, '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":1}'),
        ], [1035])->priceSteps($this->tricko());

        self::assertSame('první zdarma', $steps[0]->label);
        self::assertNull($steps[1]->label, 'Stupeň bez slevy nemá co vysvětlovat');
    }

    public function testPopisRozliseniPoctuAJestliJeZdarma(): void
    {
        $dveZdarma = $this->vypocet([
            $this->pravidlo('dve_tricka_zdarma', 'Dvě trička zdarma', 1020, '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":2}'),
        ], [1020])->priceSteps($this->tricko());
        self::assertSame('první dva zdarma', $dveZdarma[0]->label);

        $sleva = $this->vypocet([
            $this->pravidlo('tricko_levneji', 'Tričko levněji', 1035, '{"scope":"tag","effect":"percent","tag":"tricko","percent":50,"maxQuantity":3}'),
        ], [1035])->priceSteps($this->tricko());
        self::assertSame('první tři se slevou', $sleva[0]->label);
    }

    /**
     * Dva řetězené nároky dávají dva zvýhodněné stupně. Popis musí u každého mluvit
     * o rozsahu od začátku — „první dva" a pak „první tři", ne „první dva" a „první",
     * protože druhý stupeň zákazník čte jako pokračování prvního, ne jako nový počet.
     */
    public function testPopisRetezenychNarokuPocitaOdZacatku(): void
    {
        $steps = $this->vypocet([
            $this->pravidlo('dve_tricka_zdarma', 'Dvě trička zdarma', 1020, '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":2}'),
            $this->pravidlo('tricko_levneji', 'Tričko levněji', 1035, '{"scope":"tag","effect":"percent","tag":"tricko","percent":50,"maxQuantity":1}'),
        ], [1020, 1035])->priceSteps($this->tricko());

        self::assertSame('první dva zdarma', $steps[0]->label);
        self::assertSame('první tři se slevou', $steps[1]->label);
        self::assertNull($steps[2]->label);
    }

    /**
     * Česky se od pěti mění pád — „první čtyři“, ale „prvních 5“. Číslovky se píší slovem jen
     * do čtyř, dál už číslicí; obojí musí sedět, nároky na pět kusů v adminu nastavit jdou.
     */
    public function testCiselovkaMeniPadOdPeti(): void
    {
        $ctyri = $this->vypocet([
            $this->pravidlo('ctyri_zdarma', 'Čtyři zdarma', 1020, '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":4}'),
        ], [1020])->priceSteps($this->tricko());
        self::assertSame('první čtyři zdarma', $ctyri[0]->label);

        $pet = $this->vypocet([
            $this->pravidlo('pet_zdarma', 'Pět zdarma', 1020, '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":5}'),
        ], [1020])->priceSteps($this->tricko());
        self::assertSame('prvních 5 zdarma', $pet[0]->label);
    }

    /**
     * Neomezená sleva platí i na všechny další kusy, takže poslední stupeň žádný konec nemá.
     * Bez popisu by věta skončila u „první zdarma“ a zbytek žebříku by vypadal na plnou cenu.
     */
    public function testNeomezenaSlevaNaKonciZebrikuMaPopis(): void
    {
        $steps = $this->vypocet([
            $this->pravidlo('tricko_zdarma', 'Tričko zdarma', 1020, '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":1}'),
            $this->pravidlo('tricko_levneji', 'Tričko levněji', 1035, '{"scope":"tag","effect":"percent","tag":"tricko","percent":25}'),
        ], [1020, 1035])->priceSteps($this->tricko());

        self::assertCount(2, $steps);
        self::assertSame('první zdarma', $steps[0]->label);
        self::assertSame('první zdarma, další se slevou', $steps[1]->label);
    }

    /**
     * Když ocas nabízí totéž co začátek, opakovat to slovo zní krkolomně — „první tři se
     * slevou, další se slevou". Stačí říct, že to platí i dál.
     */
    public function testStejneZvyhodneniNaKonciSeNeopakuje(): void
    {
        $steps = $this->vypocet([
            $this->pravidlo('dve_zdarma', 'Dvě zdarma', 1020, '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":2}'),
            $this->pravidlo('tricko_levneji', 'Tričko levněji', 1035, '{"scope":"tag","effect":"percent","tag":"tricko","percent":50,"maxQuantity":1}'),
            $this->pravidlo('vzdy_levneji', 'Vždy levněji', 1004, '{"scope":"tag","effect":"percent","tag":"tricko","percent":10}'),
        ], [1020, 1035, 1004])->priceSteps($this->tricko());

        self::assertSame('první tři se slevou i další', $steps[2]->label);
    }

    /**
     * Žebřík, kde je jeden kus zlevněný a další zdarma, souhrnně „zdarma" není — první kus
     * se platí. „Se slevou" pokrývá obojí, takže radši obecnější slovo než nepravda; přesné
     * znění by muselo popisovat každý stupeň zvlášť a přestalo by shrnovat celý žebřík.
     */
    public function testSmisenyZebrikMluviObecneOSleve(): void
    {
        $steps = $this->vypocet([
            $this->pravidlo('tricko_levneji', 'Tričko levněji', 1035, '{"scope":"tag","effect":"percent","tag":"tricko","percent":50,"maxQuantity":1}'),
            $this->pravidlo('tricko_zdarma', 'Tričko zdarma', 1020, '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":1}'),
        ], [1020, 1035])->priceSteps($this->tricko());

        self::assertSame(200.0, $steps[0]->price);
        self::assertSame(0.0, $steps[1]->price);
        self::assertSame('první dva se slevou', $steps[1]->label);
    }

    /**
     * Stupeň za plnou cenu zůstává bez popisu — není co vysvětlovat.
     */
    public function testStupenBezSlevyNaKonciPopisNema(): void
    {
        $steps = $this->vypocet([
            $this->pravidlo('tricko_zdarma', 'Tričko zdarma', 1020, '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":1}'),
        ], [1020])->priceSteps($this->tricko());

        self::assertNull($steps[1]->label);
    }

    public function testVsechnyNarokyVycerpaneZbydePlnaCena(): void
    {
        $steps = $this->vypocet([
            $this->pravidlo('jedno_tricko_zdarma', 'Jedno tričko zdarma', 1035, '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":1}'),
        ], [1035])->priceSteps($this->tricko(), alreadyBought: 1);

        self::assertCount(1, $steps);
        self::assertSame(400.0, $steps[0]->price);
        self::assertNull($steps[0]->ruleCode);
    }

    /**
     * „Jedna kostka zdarma" je jeden nárok na všechny kostky dohromady, ne na každou zvlášť.
     * Žebřík počítá jeden produkt, takže mu musí někdo říct, kolik z kvóty už padlo jinde —
     * jinak slíbí nulu u každé ze sedmi kostek a košík je podé té ceny i naúčtuje.
     */
    public function testSpentQuotaZJinehoProduktuPlatiITady(): void
    {
        $vypocet = $this->vypocet([
            $this->pravidlo('kostka_zdarma', 'Kostka zdarma', 1003, '{"scope":"code_contains","effect":"free","codeFragment":"kostka","maxQuantity":1}'),
        ], [1003]);

        $prvni = $vypocet->priceSteps($this->kostka('fate_kostka', 30.0));
        self::assertSame(0.0, $prvni[0]->price, 'Dokud zákazník žádnou nemá, je první zdarma');

        // Zákazník mezitím koupil JINOU kostku — nárok je pryč, i když tenhle produkt
        // nikdy nekoupil.
        $druha = $vypocet->priceSteps($this->kostka('draci_kostka', 60.0), spentQuota: ['kostka_zdarma' => 1]);

        self::assertSame(60.0, $druha[0]->price, 'Druhá kostka už zdarma není');
        self::assertNull($druha[0]->label);
    }

    /**
     * Kvóta se počítá per pravidlo, ne per produkt — co se spotřebovalo na jedno pravidlo,
     * nesmí ubrat z druhého.
     */
    public function testSpotrebaJednohoPravidlaNeubiraDruhemu(): void
    {
        $steps = $this->vypocet([
            $this->pravidlo('kostka_zdarma', 'Kostka zdarma', 1003, '{"scope":"code_contains","effect":"free","codeFragment":"kostka","maxQuantity":1}'),
            $this->pravidlo('placka_zdarma', 'Placka zdarma', 1002, '{"scope":"code_contains","effect":"free","codeFragment":"placka","maxQuantity":1}'),
        ], [1002, 1003])->priceSteps($this->kostka('fate_kostka', 30.0), spentQuota: ['placka_zdarma' => 1]);

        self::assertSame(0.0, $steps[0]->price, 'Spotřebovaná placka nesmí sebrat kostku zdarma');
    }

    /**
     * Kvóta spotřebovaná jinde už zahrnuje i kusy TOHOHLE produktu, takže se nesmí
     * odečíst podruhé přes $alreadyBought. Kdo má dvě trička zdarma a jedno koupil, má
     * druhé pořád zdarma — dvojí odečet mu ho naúčtoval plnou cenou.
     */
    public function testKvotaSeNeodecitaDvakrat(): void
    {
        $pravidla = [
            $this->pravidlo('dve_tricka', 'Dvě trička zdarma', 1020, '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":2}'),
        ];
        $tricko = $this->tricko();
        $spentQuota = SpentQuota::fromPurchases($pravidla, [$tricko]);

        $steps = $this->vypocet($pravidla, [1020])->priceSteps($tricko, 1, $spentQuota);

        self::assertSame(0.0, $steps[0]->price, 'Druhé tričko z nároku na dvě je pořád zdarma');
        self::assertSame(400.0, $steps[1]->price);
    }
}
