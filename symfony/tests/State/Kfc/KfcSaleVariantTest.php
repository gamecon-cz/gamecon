<?php

declare(strict_types=1);

namespace App\Tests\State\Kfc;

use ApiPlatform\Metadata\Post;
use App\Dto\Kfc\KfcSaleInputDto;
use App\Dto\Kfc\KfcSaleItemInputDto;
use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\ProductVariant;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\State\Kfc\KfcSaleProcessor;
use App\Entity\User;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use Gamecon\Tests\Factory\UserFactory;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

/**
 * Pult musí prodat konkrétní velikost trička i konkrétní noc ubytování. Bez zadané
 * varianty se u jednovariantního předmětu dopočítá, aby starší volání nemusela nic posílat.
 */
class KfcSaleVariantTest extends AbstractDatabaseKernelTestCase
{
    private ?SystemoveNastaveni $puvodniNastaveni = null;

    /**
     * Termíny prodeje jsou PHP konstanty a pult je kontroluje — bez nich by prodej padal
     * na nedefinované konstantě, ne na tom, co testujeme. Čas se zafixuje na začátek roku,
     * aby byl prodej v termínu.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $systemoveNastaveni = SystemoveNastaveni::zGlobals();
        foreach ([
            'PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE',
            'TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE',
            'MIKINY_LZE_OBJEDNAT_A_MENIT_DO_DNE',
        ] as $klic) {
            try_define($klic, $systemoveNastaveni->dejVychoziHodnotu($klic));
        }

        $this->puvodniNastaveni = $GLOBALS['systemoveNastaveni'] ?? null;
        $GLOBALS['systemoveNastaveni'] = SystemoveNastaveni::zGlobals(
            rocnik: ROCNIK,
            ted: new DateTimeImmutableStrict(ROCNIK . '-01-01 00:00:00'),
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['systemoveNastaveni'] = $this->puvodniNastaveni;

        parent::tearDown();
    }

    private function processor(): KfcSaleProcessor
    {
        return static::getContainer()->get(KfcSaleProcessor::class);
    }

    private function prihlasOperatora(): User
    {
        /** @var User $operator */
        $operator = UserFactory::createOne([
            UserEntityStructure::login => 'kfc_var_' . uniqid(),
            UserEntityStructure::email => 'kfc_var_' . uniqid() . '@example.invalid',
        ])->_save()->_real();

        static::getContainer()->get('security.token_storage')->setToken(
            new PostAuthenticationToken($operator, 'main', ['ROLE_ADMIN']),
        );

        return $operator;
    }

    /**
     * @param string[] $velikosti
     */
    private function vytvorPredmet(array $velikosti): Product
    {
        $this->connection()->executeStatement(
            'INSERT IGNORE INTO product_tag (code, name, created_at) VALUES (:code, :name, NOW())',
            [
                'code' => ProductTagCode::PREDMET->value,
                'name' => 'Předmět',
            ],
        );
        $tag = $this->entityManager()
            ->getRepository(ProductTag::class)
            ->findOneBy([
                'code' => ProductTagCode::PREDMET->value,
            ]);
        self::assertNotNull($tag);

        $kod = 'kfc-var-' . uniqid();

        $predmet = new Product();
        $predmet->setName('Tričko');
        $predmet->setCode($kod);
        $predmet->setCurrentPrice('400.00');
        $predmet->setDescription('');
        $predmet->setState(ProductStateEnum::PUBLIC);
        $predmet->setProducedQuantity(10);
        $predmet->addTag($tag);
        $this->entityManager()->persist($predmet);

        foreach ($velikosti as $poradi => $velikost) {
            $varianta = new ProductVariant();
            $varianta->setProduct($predmet);
            $varianta->setName($velikost);
            $varianta->setCode($kod . '-' . $velikost);
            $varianta->setPrice('400.00');
            $varianta->setRemainingQuantity(10);
            $varianta->setPosition($poradi);
            $predmet->addVariant($varianta);
            $this->entityManager()->persist($varianta);
        }
        $this->entityManager()->flush();

        return $predmet;
    }

    private function prodej(Product $predmet, ?int $idVarianty): KfcSaleInputDto
    {
        $polozka = new KfcSaleItemInputDto();
        $polozka->productId = (int) $predmet->getId();
        $polozka->variantId = $idVarianty;
        $polozka->quantity = 1;

        $vstup = new KfcSaleInputDto();
        $vstup->items[] = $polozka;

        return $vstup;
    }

    private function zbyvaNaVariante(ProductVariant $varianta): ?int
    {
        $this->entityManager()->refresh($varianta);

        return $varianta->getRemainingQuantity();
    }

    public function testProdaZvolenouVelikost(): void
    {
        $this->prihlasOperatora();
        $predmet = $this->vytvorPredmet(['S', 'M', 'L']);
        /** @var ProductVariant $emko */
        $emko = $predmet->getVariants()->get(1);
        self::assertSame('M', $emko->getName());

        $this->processor()->process($this->prodej($predmet, (int) $emko->getId()), new Post());

        // Odečíst se musí právě ta velikost, kterou si zákazník vzal.
        self::assertSame(9, $this->zbyvaNaVariante($emko));
        /** @var ProductVariant $esko */
        $esko = $predmet->getVariants()->get(0);
        self::assertSame(10, $this->zbyvaNaVariante($esko));
    }

    public function testBezVariantyUVicVariantOdmitne(): void
    {
        $this->prihlasOperatora();
        $predmet = $this->vytvorPredmet(['S', 'M', 'L']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('má víc variant');

        $this->processor()->process($this->prodej($predmet, null), new Post());
    }

    public function testVariantaZJinehoPredmetuOdmitne(): void
    {
        $this->prihlasOperatora();
        $predmet = $this->vytvorPredmet(['S', 'M']);
        $cizi = $this->vytvorPredmet(['XL']);
        /** @var ProductVariant $cizivarianta */
        $cizivarianta = $cizi->getVariants()->get(0);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('nepatří k produktu');

        $this->processor()->process($this->prodej($predmet, (int) $cizivarianta->getId()), new Post());
    }

    public function testJednovariantniPredmetVariantuNepotrebuje(): void
    {
        $this->prihlasOperatora();
        $predmet = $this->vytvorPredmet(['jedna velikost']);
        /** @var ProductVariant $varianta */
        $varianta = $predmet->getVariants()->get(0);

        $this->processor()->process($this->prodej($predmet, null), new Post());

        self::assertSame(9, $this->zbyvaNaVariante($varianta));
    }

    public function testPredmetBezVariantyProdatNejde(): void
    {
        // Osiřelé řádky po rozpadu velikostí do variant — na mřížkách jich je 28.
        $this->prihlasOperatora();
        $predmet = $this->vytvorPredmet([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('nemá žádnou variantu');

        $this->processor()->process($this->prodej($predmet, null), new Post());
    }
}
