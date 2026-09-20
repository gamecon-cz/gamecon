<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Service\JwtService;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use Doctrine\DBAL\Connection;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Factory\UserFactory;

/**
 * Prodej na pultu přes HTTP. Co se smí prodat, rozhoduje až prodej — nabídka vrací i to,
 * co prodat nejde, protože ji čte i editor mřížek. Tyhle testy drží tu hranici: přes API
 * nesmí projít stažený, archivní ani vyprodaný předmět, ani varianta cizího produktu.
 */
class KfcSaleApiTest extends AbstractDatabaseKernelTestCase
{
    private ?SystemoveNastaveni $puvodniNastaveni = null;

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    /**
     * Termíny prodeje jsou PHP konstanty a pult je kontroluje; bez nich by požadavek padal
     * na nedefinované konstantě místo na tom, co se testuje.
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

    private function adminClient(): Client
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);

        $adminRoleId = $connection->fetchOne(
            "SELECT id_role FROM role_seznam
             WHERE LOWER(kod_role) IN ('organizator', 'admin', 'infopult', 'cfo')
             ORDER BY id_role LIMIT 1",
        );
        if ($adminRoleId === false) {
            $this->markTestSkipped('No admin-granting role in role_seznam');
        }

        /** @var User $operator */
        $operator = UserFactory::createOne([
            UserEntityStructure::login => 'kfc_api_' . uniqid(),
            UserEntityStructure::email => 'kfc_api_' . uniqid() . '@example.invalid',
        ])->_save()->_real();

        $connection->executeStatement(
            'INSERT INTO uzivatele_role (id_uzivatele, id_role, posazen) VALUES (?, ?, NOW())',
            [$operator->getId(), $adminRoleId],
        );
        // Role vznikla SQL, takže už načtený User o ní neví.
        $this->entityManager()->clear();

        /** @var User $cerstvy */
        $cerstvy = $this->entityManager()->getRepository(User::class)->find($operator->getId());

        /** @var JwtService $jwtService */
        $jwtService = static::getContainer()->get(JwtService::class);

        return $this->jsonLdClient([
            'Authorization' => 'Bearer ' . $jwtService->generateJwtToken($jwtService->extractUserData($cerstvy)),
        ]);
    }

    /**
     * @param array<string, int|null> $velikostiSeZasobou název => zbývá
     */
    private function vytvorPredmet(
        array            $velikostiSeZasobou,
        ProductStateEnum $stav = ProductStateEnum::PUBLIC,
        ?string          $archivedAt = null,
    ): Product {
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

        $kod = 'kfc-api-' . uniqid();

        $predmet = new Product();
        $predmet->setName('Tričko');
        $predmet->setCode($kod);
        $predmet->setCurrentPrice('400.00');
        $predmet->setDescription('');
        $predmet->setState($stav);
        $predmet->setProducedQuantity(10);
        $predmet->addTag($tag);
        if ($archivedAt !== null) {
            $predmet->setArchivedAt(new \DateTimeImmutable($archivedAt));
        }
        $this->entityManager()->persist($predmet);

        $poradi = 0;
        foreach ($velikostiSeZasobou as $velikost => $zbyva) {
            $varianta = new ProductVariant();
            $varianta->setProduct($predmet);
            $varianta->setName((string) $velikost);
            $varianta->setCode($kod . '-' . $velikost);
            $varianta->setPrice('400.00');
            $varianta->setRemainingQuantity($zbyva);
            $varianta->setPosition($poradi++);
            $predmet->addVariant($varianta);
            $this->entityManager()->persist($varianta);
        }
        $this->entityManager()->flush();

        return $predmet;
    }

    /** Stav po požadavku se čte z databáze — `adminClient()` mezitím vyčistil EntityManager. */
    private function zbyvaNaVariante(int $idVarianty): ?int
    {
        $zbyva = $this->connection()->fetchOne(
            'SELECT remaining_quantity FROM product_variant WHERE id = :id',
            [
                'id' => $idVarianty,
            ],
        );

        return $zbyva === null ? null : (int) $zbyva;
    }

    private function varianta(Product $predmet, string $nazev): ProductVariant
    {
        foreach ($predmet->getVariants() as $varianta) {
            if ($varianta->getName() === $nazev) {
                return $varianta;
            }
        }

        self::fail(sprintf('Varianta "%s" nenalezena.', $nazev));
    }

    /**
     * @return array{status: int, telo: string}
     */
    private function prodej(Client $client, Product $predmet, ?ProductVariant $varianta, int $mnozstvi = 1): array
    {
        $polozka = [
            'productId' => (int) $predmet->getId(),
            'quantity'  => $mnozstvi,
        ];
        if ($varianta !== null) {
            $polozka['variantId'] = (int) $varianta->getId();
        }

        $response = $client->request('POST', '/symfony/api/kfc/sale', [
            'json' => [
                'items' => [$polozka],
            ],
        ]);

        return [
            'status' => $response->getStatusCode(),
            'telo'   => $response->getContent(false),
        ];
    }

    public function testProdaZvolenouVelikost(): void
    {
        $predmet = $this->vytvorPredmet([
            'S' => 10,
            'M' => 10,
        ]);
        $emko = $this->varianta($predmet, 'M');
        $idEmka = (int) $emko->getId();
        $idEska = (int) $this->varianta($predmet, 'S')->getId();

        $odpoved = $this->prodej($this->adminClient(), $predmet, $emko);

        self::assertSame(201, $odpoved['status'], $odpoved['telo']);

        self::assertSame(9, $this->zbyvaNaVariante($idEmka));
        // Sourozenec se odečíst nesmí.
        self::assertSame(10, $this->zbyvaNaVariante($idEska));
    }

    public function testStazenyPredmetNeprodaAniSVariantou(): void
    {
        // Nabídka ho vrací kvůli mřížkám, prodej ho ale odmítnout musí.
        $predmet = $this->vytvorPredmet([
            'S' => 10,
        ], stav: ProductStateEnum::RETIRED);

        $odpoved = $this->prodej($this->adminClient(), $predmet, $this->varianta($predmet, 'S'));

        self::assertNotSame(201, $odpoved['status']);
        self::assertStringContainsString('není dostupný', $odpoved['telo']);
    }

    public function testArchivniPredmetNeproda(): void
    {
        $predmet = $this->vytvorPredmet([
            'S' => 10,
        ], archivedAt: '2025-12-31 23:59:59');

        $odpoved = $this->prodej($this->adminClient(), $predmet, $this->varianta($predmet, 'S'));

        self::assertNotSame(201, $odpoved['status']);
        self::assertStringContainsString('není dostupný', $odpoved['telo']);
    }

    public function testVariantaCizihoPredmetuNeproda(): void
    {
        $predmet = $this->vytvorPredmet([
            'S' => 10,
        ]);
        $cizi = $this->vytvorPredmet([
            'XL' => 10,
        ]);

        $odpoved = $this->prodej($this->adminClient(), $predmet, $this->varianta($cizi, 'XL'));

        self::assertNotSame(201, $odpoved['status']);
        self::assertStringContainsString('nepatří k produktu', $odpoved['telo']);
    }

    /**
     * Zásobu drží varianta, ne produkt — prodej přes ni nesmí projít ani tehdy, když
     * sourozenci mají kusů dost.
     */
    public function testNeprodaVicNezZbyvaNaVariante(): void
    {
        $predmet = $this->vytvorPredmet([
            'S' => 10,
            'M' => 1,
        ]);
        $emko = $this->varianta($predmet, 'M');
        $idEmka = (int) $emko->getId();

        $odpoved = $this->prodej($this->adminClient(), $predmet, $emko, mnozstvi: 2);

        self::assertNotSame(201, $odpoved['status'], $odpoved['telo']);

        // Neúspěšný prodej nesmí zásobu ukousnout ani zčásti.
        self::assertSame(1, $this->zbyvaNaVariante($idEmka));
    }

    public function testBezVariantyUVicVariantNeproda(): void
    {
        $predmet = $this->vytvorPredmet([
            'S' => 10,
            'M' => 10,
        ]);

        $odpoved = $this->prodej($this->adminClient(), $predmet, null);

        self::assertNotSame(201, $odpoved['status']);
        self::assertStringContainsString('má víc variant', $odpoved['telo']);
    }
}
