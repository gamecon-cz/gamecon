<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\PermissionEnum;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Service\JwtService;
use App\Service\MealMenuSchedule;
use App\Service\MealWriter;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use Gamecon\Tests\Factory\UserFactory;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Jídelníček přes HTTP: kdo ho smí upravovat a že účastník vybírá jen ke svým porcím.
 * Pravidla visí na operacích, takže je přímo zavolaný provider nepotká.
 */
class MealMenuApiTest extends AbstractDatabaseKernelTestCase
{
    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Jídelníček je zveřejněný a výběr ještě běží. Termíny jdou z nastavení, které má
        // testovací databáze z jiného ročníku, takže si je test určí sám.
        static::getContainer()->set(MealMenuSchedule::class, new class extends MealMenuSchedule {
            public function isPublished(): bool
            {
                return true;
            }

            public function getPublishedFrom(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-07-02');
            }

            public function isChoiceClosed(): bool
            {
                return false;
            }

            public function getChoiceDeadline(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-07-15');
            }
        });
    }

    public function testAnonymousCannotReadTheMenu(): void
    {
        $response = $this->jsonLdClient()->request('GET', '/symfony/api/cart/meal-menu');

        self::assertSame(401, $response->getStatusCode());
    }

    public function testEditingTheMenuNeedsTheFinanceRight(): void
    {
        $response = $this->request($this->user('menu_nobody_'), 'GET', '/symfony/api/admin/meal-menu');

        self::assertSame(403, $response->getStatusCode(), $response->getContent(false));
        self::assertSame('Na úpravu jídelníčku nemáš právo.', $response->toArray(false)['detail'] ?? null);
    }

    public function testParticipantChoosesADishForTheirOwnPortion(): void
    {
        $editor = $this->operator(PermissionEnum::ADMINISTRACE_FINANCE);
        $obed = $this->meal();
        $menu = $this->request($editor, 'POST', '/symfony/api/admin/meal-menu', [
            'variantId' => $obed->getId(),
            'dishes'    => [
                [
                    'name' => 'Guláš',
                ],
                [
                    'name'       => 'Rizoto',
                    'vegetarian' => true,
                ],
            ],
        ]);
        self::assertSame(201, $menu->getStatusCode(), $menu->getContent(false));
        $rizoto = $this->dishId($menu->toArray(), $obed, 'Rizoto');

        $ucastnik = $this->user('menu_eater_');
        static::getContainer()->get(MealWriter::class)->save($ucastnik, [(int) $obed->getId()], ROCNIK);
        $porce = $this->portionId($this->request($ucastnik, 'GET', '/symfony/api/cart/meal-menu')->toArray(), $obed);

        $vyber = $this->request($ucastnik, 'POST', '/symfony/api/cart/meal-menu', [
            'orderItemId' => $porce,
            'dishId'      => $rizoto,
        ]);

        self::assertSame(201, $vyber->getStatusCode(), $vyber->getContent(false));
        self::assertSame($rizoto, $this->slot($vyber->toArray(), $obed)['portions'][0]['chosenDishId']);

        $ciziVyber = $this->request($this->user('menu_stranger_'), 'POST', '/symfony/api/cart/meal-menu', [
            'orderItemId' => $porce,
            'dishId'      => null,
        ]);
        self::assertSame(400, $ciziVyber->getStatusCode(), 'Cizí porci nejde měnit');
    }

    /**
     * @param array<string, mixed> $menu
     *
     * @return array<string, mixed>
     */
    private function slot(array $menu, ProductVariant $porce): array
    {
        foreach ($menu['slots'] as $slot) {
            if ($slot['variantId'] === $porce->getId()) {
                return $slot;
            }
        }

        self::fail('Slot v jídelníčku chybí.');
    }

    /**
     * @param array<string, mixed> $menu
     */
    private function dishId(array $menu, ProductVariant $porce, string $nazev): int
    {
        foreach ($this->slot($menu, $porce)['dishes'] as $jidlo) {
            if ($jidlo['name'] === $nazev) {
                return $jidlo['id'];
            }
        }

        self::fail("Jídlo {$nazev} v jídelníčku není.");
    }

    /**
     * @param array<string, mixed> $menu
     */
    private function portionId(array $menu, ProductVariant $porce): int
    {
        $porce = $this->slot($menu, $porce)['portions'];
        self::assertCount(1, $porce);

        return $porce[0]['orderItemId'];
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function request(User $caller, string $method, string $uri, ?array $body = null): ResponseInterface
    {
        return $this->clientFor($caller)->request($method, $uri, $body === null
            ? []
            : [
                'json' => $body,
            ]);
    }

    private function clientFor(User $user): Client
    {
        /** @var JwtService $jwtService */
        $jwtService = static::getContainer()->get(JwtService::class);

        return $this->jsonLdClient([
            'Authorization' => 'Bearer ' . $jwtService->generateJwtToken($jwtService->extractUserData($user)),
        ]);
    }

    private function user(string $loginPrefix): User
    {
        /** @var User $user */
        $user = UserFactory::createOne([
            UserEntityStructure::login => $loginPrefix . uniqid(),
            UserEntityStructure::email => $loginPrefix . uniqid() . '@example.invalid',
        ])->_save()->_real();

        return $user;
    }

    private function operator(PermissionEnum $permission): User
    {
        $operator = $this->user('menu_editor_');
        $roleId = $this->connection()->fetchOne(
            'SELECT prava_role.id_role
             FROM prava_role
             INNER JOIN role_seznam ON role_seznam.id_role = prava_role.id_role
             WHERE prava_role.id_prava = :pravo AND role_seznam.rocnik_role IN (:rok, -1)
             LIMIT 1',
            [
                'pravo' => $permission->value,
                'rok'   => ROCNIK,
            ],
        );
        self::assertNotFalse($roleId, sprintf('Some role of this year must grant %s', $permission->name));

        $this->connection()->executeStatement(
            'INSERT INTO uzivatele_role (id_uzivatele, id_role) VALUES (:uzivatel, :role)',
            [
                'uzivatel' => $operator->getId(),
                'role'     => $roleId,
            ],
        );

        return $operator;
    }

    private function meal(): ProductVariant
    {
        $this->connection()->executeStatement(
            'INSERT IGNORE INTO product_tag (code, name, created_at) VALUES (:code, :name, NOW())',
            [
                'code' => ProductTagCode::JIDLO->value,
                'name' => 'Jídlo',
            ],
        );
        $tag = $this->entityManager()->getRepository(ProductTag::class)->findOneBy([
            'code' => ProductTagCode::JIDLO->value,
        ]);
        self::assertNotNull($tag);

        $code = 'MENU-TEST-' . uniqid();
        $meal = new Product();
        $meal->setName('Oběd pátek');
        $meal->setCode($code);
        $meal->setCurrentPrice('140.00');
        $meal->setDescription('');
        $meal->setState(ProductStateEnum::PUBLIC);
        $meal->setAccommodationDay(2);
        $meal->addTag($tag);
        $this->entityManager()->persist($meal);

        $variant = new ProductVariant();
        $variant->setProduct($meal);
        $variant->setCode($code . '-V');
        $variant->setPrice('140.00');
        $variant->setPosition(0);
        $meal->addVariant($variant);
        $this->entityManager()->persist($variant);
        $this->entityManager()->flush();

        return $variant;
    }
}
