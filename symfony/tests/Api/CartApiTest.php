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
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Factory\UserFactory;

/**
 * What the participant sees when the cart refuses an item: a status saying why, and a message
 * they can read. A refusal is not a server error.
 */
class CartApiTest extends AbstractDatabaseKernelTestCase
{
    private ?SystemoveNastaveni $puvodniNastaveni = null;

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

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

    public function testStazenyPredmetOdmitneJakoNedostupnyNeJakoChybuServeru(): void
    {
        $varianta = $this->variantaPredmetu(ProductStateEnum::RETIRED);

        $odpoved = $this->zakaznik()->request('POST', '/symfony/api/cart/items', [
            'json' => [
                'variantId' => (int) $varianta->getId(),
            ],
        ]);

        self::assertSame(409, $odpoved->getStatusCode(), $odpoved->getContent(false));
        self::assertStringContainsString('není dostupný', $odpoved->toArray(false)['detail'] ?? '');
    }

    private function zakaznik(): Client
    {
        /** @var User $zakaznik */
        $zakaznik = UserFactory::createOne([
            UserEntityStructure::login => 'api_test_' . uniqid(),
            UserEntityStructure::email => 'api_test_' . uniqid() . '@example.invalid',
        ])->_save()->_real();

        /** @var JwtService $jwtService */
        $jwtService = static::getContainer()->get(JwtService::class);

        return $this->jsonLdClient([
            'Authorization' => 'Bearer ' . $jwtService->generateJwtToken($jwtService->extractUserData($zakaznik)),
        ]);
    }

    private function variantaPredmetu(ProductStateEnum $stav): ProductVariant
    {
        $this->connection()->executeStatement(
            'INSERT IGNORE INTO product_tag (code, name, created_at) VALUES (:code, :name, NOW())',
            [
                'code' => ProductTagCode::PREDMET->value,
                'name' => 'Předmět',
            ],
        );
        $tag = $this->entityManager()->getRepository(ProductTag::class)->findOneBy([
            'code' => ProductTagCode::PREDMET->value,
        ]);
        self::assertNotNull($tag);

        $kod = 'API-TEST-' . uniqid();
        $predmet = new Product();
        $predmet->setName('Placka');
        $predmet->setCode($kod);
        $predmet->setCurrentPrice('40.00');
        $predmet->setDescription('');
        $predmet->setState($stav);
        $predmet->addTag($tag);
        $this->entityManager()->persist($predmet);

        $varianta = new ProductVariant();
        $varianta->setProduct($predmet);
        $varianta->setName('Placka');
        $varianta->setCode($kod . '-V');
        $varianta->setPrice('40.00');
        $varianta->setPosition(0);
        $predmet->addVariant($varianta);
        $this->entityManager()->persist($varianta);
        $this->entityManager()->flush();

        return $varianta;
    }
}
