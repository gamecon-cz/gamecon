<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Discount\DiscountRuleLoader;
use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\User;
use App\Enum\ProductTagCode;
use App\Service\DiscountCalculator;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use PHPUnit\Framework\TestCase;

/**
 * Slevy ve storefrontu se musí počítat ze stejných pravidel jako v legacy Ceniku — jinak
 * účastník vidí v e-shopu jinou cenu, než jakou mu spočítají finance.
 *
 * Testuje se proti skutečnému motoru (DiscountCalculation), ne proti mocku repozitáře:
 * předchozí verze četla jinou, prázdnou tabulku a sada testů nad mockem to nezachytila.
 */
class DiscountCalculatorTest extends TestCase
{
    private const ROK = 2026;

    private const PRAVO_SLEVA_NA_JIDLO = 1004;

    private const PRAVO_UBYTOVANI_ZDARMA = 1008;

    private const PRAVIDLO_JIDLO = [
        'code'           => 'jidlo_se_slevou',
        'name'           => 'Sleva orga na jídlo',
        'required_right' => self::PRAVO_SLEVA_NA_JIDLO,
        'parameters'     => '{"scope":"tag","effect":"fixed_amount","tag":"jidlo","amountSetting":"organizerMealDiscount"}',
    ];

    /**
     * @param array<int, array<string, mixed>> $pravidla
     * @param int[]                            $prava
     */
    private function kalkulator(array $pravidla, array $prava): DiscountCalculator
    {
        $fetchAll = static function (string $sql) use ($pravidla, $prava): array {
            if (str_contains($sql, 'discount_rule')) {
                return $pravidla;
            }

            return array_map(static fn (int $pravo): array => [
                'id_prava' => $pravo,
            ], $prava);
        };

        $systemoveNastaveni = $this->createMock(SystemoveNastaveni::class);
        $systemoveNastaveni->method('slevaOrguNaJidloCastka')->willReturn(30.0);
        $systemoveNastaveni->method('modreTrickoZdarmaOd')->willReturn(1000.0);

        return new DiscountCalculator(new DiscountRuleLoader($fetchAll), $systemoveNastaveni);
    }

    private function produkt(string $nazev, string $cena, ?ProductTagCode $tag): Product
    {
        $product = new Product();
        $product->setName($nazev);
        $product->setCode(mb_strtolower($nazev));
        $product->setCurrentPrice($cena);
        $product->setDescription('');

        if ($tag !== null) {
            $productTag = new ProductTag();
            $productTag->setCode($tag->value);
            $productTag->setName($tag->value);
            $product->addTag($productTag);
        }

        $reflexe = new \ReflectionProperty(Product::class, 'id');
        $reflexe->setValue($product, 42);

        return $product;
    }

    private function uzivatel(): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(6474);

        return $user;
    }

    public function testPravoNaSlevuJidloZlevni(): void
    {
        $vysledek = $this->kalkulator([self::PRAVIDLO_JIDLO], [self::PRAVO_SLEVA_NA_JIDLO])
            ->calculateDiscount($this->produkt('Oběd čtvrtek', '140.00', ProductTagCode::JIDLO), $this->uzivatel(), self::ROK);

        self::assertSame('110.00', $vysledek['finalPrice']);
        self::assertSame('30.00', $vysledek['discountAmount']);
        self::assertSame('Sleva orga na jídlo', $vysledek['reason']);
    }

    public function testBezPravaPlnaCena(): void
    {
        $vysledek = $this->kalkulator([self::PRAVIDLO_JIDLO], [self::PRAVO_UBYTOVANI_ZDARMA])
            ->calculateDiscount($this->produkt('Oběd čtvrtek', '140.00', ProductTagCode::JIDLO), $this->uzivatel(), self::ROK);

        self::assertSame('140.00', $vysledek['finalPrice']);
        self::assertSame('0.00', $vysledek['discountAmount']);
        self::assertNull($vysledek['reason']);
    }

    /**
     * Pravidlo míří na tag `jidlo`; merch se ho nesmí chytit, i když kupující právo má.
     */
    public function testPravidloSeNevztahujeNaJinyTag(): void
    {
        $vysledek = $this->kalkulator([self::PRAVIDLO_JIDLO], [self::PRAVO_SLEVA_NA_JIDLO])
            ->calculateDiscount($this->produkt('Placka', '40.00', ProductTagCode::PREDMET), $this->uzivatel(), self::ROK);

        self::assertSame('40.00', $vysledek['finalPrice']);
    }

    public function testUbytovaniZdarmaJeZdarma(): void
    {
        $vysledek = $this->kalkulator([
            [
                'code'           => 'ubytovani_zdarma',
                'name'           => 'Ubytování zdarma',
                'required_right' => self::PRAVO_UBYTOVANI_ZDARMA,
                'parameters'     => '{"scope":"tag","effect":"free","tag":"ubytovani"}',
            ],
        ], [self::PRAVO_UBYTOVANI_ZDARMA])
            ->calculateDiscount($this->produkt('Postel na 2L koleji', '500.00', ProductTagCode::UBYTOVANI), $this->uzivatel(), self::ROK);

        self::assertSame('0.00', $vysledek['finalPrice']);
        self::assertSame('500.00', $vysledek['discountAmount']);
    }

    /**
     * Noc zdarma se váže na den, jenže den nese varianta — typ pokoje žádný nemá. Bez
     * předaného dne by nárok na konkrétní noc nikdy nesedl a účastník by ji zaplatil.
     */
    public function testNightSpecificDiscountUsesTheGivenDay(): void
    {
        $kalkulator = $this->kalkulator([
            [
                'code'           => 'ubytovani_patecni_noc_zdarma',
                'name'           => 'Ubytování páteční noc zdarma',
                'required_right' => self::PRAVO_UBYTOVANI_ZDARMA,
                'parameters'     => '{"scope":"tag_and_day","effect":"free","tag":"ubytovani","day":2}',
            ],
        ], [self::PRAVO_UBYTOVANI_ZDARMA]);
        $typPokoje = $this->produkt('Postel na 2L koleji', '500.00', ProductTagCode::UBYTOVANI);

        $patek = $kalkulator->calculateDiscount($typPokoje, $this->uzivatel(), self::ROK, accommodationDay: 2);
        $ctvrtek = $kalkulator->calculateDiscount($typPokoje, $this->uzivatel(), self::ROK, accommodationDay: 1);

        self::assertSame('0.00', $patek['finalPrice'], 'Na páteční noc nárok platí');
        self::assertSame('500.00', $ctvrtek['finalPrice'], 'Na čtvrteční ne');
    }

    /**
     * Metoda počítá jednu položku, takže nároky s omezeným počtem neumí odpočítávat.
     * Kdyby je uplatňovala, bylo by každé tričko zdarma — a to i při zápisu do košíku.
     */
    public function testPravidloSOmezenymPoctemSeNeuplatni(): void
    {
        $kalkulator = $this->kalkulator([
            [
                'code'           => 'jedno_tricko_zdarma',
                'name'           => 'Jedno tričko zdarma',
                'required_right' => self::PRAVO_UBYTOVANI_ZDARMA,
                'parameters'     => '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":1}',
            ],
        ], [self::PRAVO_UBYTOVANI_ZDARMA]);

        $prvni = $kalkulator->calculateDiscount($this->produkt('Tričko A', '400.00', ProductTagCode::TRICKO), $this->uzivatel(), self::ROK);
        $druhe = $kalkulator->calculateDiscount($this->produkt('Tričko B', '400.00', ProductTagCode::TRICKO), $this->uzivatel(), self::ROK);

        self::assertSame('400.00', $prvni['finalPrice']);
        self::assertSame('400.00', $druhe['finalPrice'], 'Druhé tričko nesmí být zdarma jen proto, že se počítá zvlášť');
    }

    /**
     * Co mřížka slíbí, to musí košík naúčtovat. Dřív mřížka ukazovala „0 Kč / 400 Kč
     * (první zdarma)" a košík účtoval 400 — nárok s omezeným počtem se do ceny jedné
     * položky nevešel, kdežto do žebříku ano.
     */
    public function testCenaDalsihoKusuSediSPrvnimStupnemZebriku(): void
    {
        $pravidlo = [
            'code'           => 'jedno_tricko_zdarma',
            'name'           => 'Jedno tričko zdarma',
            'required_right' => self::PRAVO_UBYTOVANI_ZDARMA,
            'parameters'     => '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":1}',
        ];
        $kalkulator = $this->kalkulator([$pravidlo], [self::PRAVO_UBYTOVANI_ZDARMA]);
        $tricko = $this->produkt('Tričko', '400.00', ProductTagCode::TRICKO);

        foreach ([0, 1, 2] as $alreadyBought) {
            $zebrik = $kalkulator->priceSteps($tricko, $this->uzivatel(), self::ROK, $alreadyBought);
            $ucet = $kalkulator->priceForNextPiece($tricko, $this->uzivatel(), self::ROK, $alreadyBought);

            self::assertSame(
                $zebrik[0]['price'],
                $ucet['finalPrice'],
                sprintf('Po %d koupených se cena v mřížce a v košíku rozešla', $alreadyBought),
            );
        }
    }

    /**
     * První kus zdarma, druhý už za své — to je celý smysl nároku s omezeným počtem.
     */
    public function testPrvniKusZdarmaDruhyZaPlnou(): void
    {
        $kalkulator = $this->kalkulator([
            [
                'code'           => 'jedno_tricko_zdarma',
                'name'           => 'Jedno tričko zdarma',
                'required_right' => self::PRAVO_UBYTOVANI_ZDARMA,
                'parameters'     => '{"scope":"tag","effect":"free","tag":"tricko","maxQuantity":1}',
            ],
        ], [self::PRAVO_UBYTOVANI_ZDARMA]);
        $tricko = $this->produkt('Tričko', '400.00', ProductTagCode::TRICKO);

        self::assertSame('0.00', $kalkulator->priceForNextPiece($tricko, $this->uzivatel(), self::ROK, 0)['finalPrice']);
        self::assertSame('400.00', $kalkulator->priceForNextPiece($tricko, $this->uzivatel(), self::ROK, 1)['finalPrice']);
    }

    public function testProduktBezTaguNemaSlevu(): void
    {
        $vysledek = $this->kalkulator([self::PRAVIDLO_JIDLO], [self::PRAVO_SLEVA_NA_JIDLO])
            ->calculateDiscount($this->produkt('Vstupné', '250.00', null), $this->uzivatel(), self::ROK);

        self::assertSame('250.00', $vysledek['finalPrice']);
    }
}
