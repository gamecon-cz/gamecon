<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\MealMenu\MealDishInputDto;
use App\Dto\MealMenu\MealMenuOutputDto;
use App\Dto\MealMenu\MealMenuSlotOutputDto;
use App\Entity\MealDish;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Exception\InvalidRequestException;
use App\Exception\NoLongerAvailableException;
use App\Repository\MealDishRepository;
use App\Repository\OrderItemRepository;
use App\Repository\ProductRepository;
use App\Service\CurrentYearProviderInterface;
use App\Service\MealDishChooser;
use App\Service\MealMenuEditor;
use App\Service\MealMenuReader;
use App\Service\MealMenuSchedule;
use App\Service\MealWriter;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use App\Tests\Support\ChybovePreklady;
use Gamecon\Role\Role;
use Gamecon\Tests\Factory\UserFactory;

/**
 * Jídelníček: 14 dní před GameConem se k objednaným obědům a večeřím vybírá konkrétní jídlo.
 * Kdo si nevybere, dostane výchozí jídlo slotu.
 */
class MealMenuTest extends AbstractDatabaseKernelTestCase
{
    private bool $zverejneno = true;

    private bool $vyberUzavren = false;

    private int $rok;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rok = static::getContainer()->get(CurrentYearProviderInterface::class)->getCurrentYear();
    }

    /**
     * @test
     */
    public function slotyJsouObedyAVecerePoDnechBezSnidani(): void
    {
        $vecerePatek = $this->vytvorJidlo('Večeře pátek', 2);
        $snidanePatek = $this->vytvorJidlo('Snídaně pátek', 2, snidane: true);
        $obedPatek = $this->vytvorJidlo('Oběd pátek', 2);
        $obedCtvrtek = $this->vytvorJidlo('Oběd čtvrtek', 1);

        $sloty = $this->mojeSloty($this->reader()->getSlots($this->rok), [$vecerePatek, $snidanePatek, $obedPatek, $obedCtvrtek]);

        self::assertSame(['Oběd čtvrtek', 'Oběd pátek', 'Večeře pátek'], array_map(static fn ($slot): string => $slot->name, $sloty));
        self::assertSame('pátek', $sloty[1]->getDayName());
    }

    /**
     * Produkt jídla přežívá ročníky, jídla jsou ale každý rok jiná.
     *
     * @test
     */
    public function lonskaJidlaVLetosnimJidelnickuNejsou(): void
    {
        $obed = $this->vytvorJidlo('Oběd pátek', 2);
        $this->entityManager()->persist(new MealDish($obed, $this->rok - 1, 'Loňská svíčková'));
        $this->entityManager()->flush();
        $this->editor()->saveSlot($obed->getId(), [$this->jidlo('Letošní guláš')], $this->rok);

        $slot = $this->reader()->findSlot($obed->getId(), $this->rok);

        self::assertNotNull($slot);
        self::assertSame(['Letošní guláš'], array_map(static fn (MealDish $dish): string => $dish->getName(), $slot->dishes));
    }

    /**
     * @test
     */
    public function predZverejnenimUcastnikJidlaNevidiAlePultAno(): void
    {
        $obed = $this->vytvorJidlo('Oběd pátek', 2);
        $this->editor()->saveSlot($obed->getId(), [$this->jidlo('Guláš'), $this->jidlo('Rizoto', vegetarian: true)], $this->rok);
        $zakaznik = $this->zakaznik();
        $this->kup($zakaznik, $obed);
        $this->zverejneno = false;

        $ucastnik = $this->slotMenu($this->reader()->forCustomer($zakaznik, $this->rok, false), $obed);
        $pult = $this->slotMenu($this->reader()->forCustomer($zakaznik, $this->rok, true), $obed);

        self::assertSame([], $ucastnik->dishes);
        self::assertCount(1, $ucastnik->portions, 'Porci vidí, jen k ní ještě nejde vybrat');
        self::assertNull($ucastnik->portions[0]->effectiveDishId);
        self::assertCount(2, $pult->dishes);
    }

    /**
     * @test
     */
    public function kdoNevybereDostaneVychoziJidlo(): void
    {
        $obed = $this->vytvorJidlo('Oběd pátek', 2);
        $this->editor()->saveSlot($obed->getId(), [$this->jidlo('Guláš'), $this->jidlo('Rizoto', default: true)], $this->rok);
        $zakaznik = $this->zakaznik();
        $this->kup($zakaznik, $obed);

        $slot = $this->slotMenu($this->reader()->forCustomer($zakaznik, $this->rok, false), $obed);

        self::assertNull($slot->portions[0]->chosenDishId);
        self::assertSame($this->idJidla($obed, 'Rizoto'), $slot->portions[0]->effectiveDishId);
        self::assertSame([false, true], array_map(static fn ($dish): bool => $dish->default, $slot->dishes));
    }

    /**
     * @test
     */
    public function vybraneJidloSeUlozi(): void
    {
        $obed = $this->vytvorJidlo('Oběd pátek', 2);
        $this->editor()->saveSlot($obed->getId(), [$this->jidlo('Guláš'), $this->jidlo('Rizoto')], $this->rok);
        $zakaznik = $this->zakaznik();
        $porce = $this->kup($zakaznik, $obed);
        $rizoto = $this->idJidla($obed, 'Rizoto');

        $this->chooser()->choose($zakaznik, $porce, $rizoto, $this->rok, false);

        $slot = $this->slotMenu($this->reader()->forCustomer($zakaznik, $this->rok, false), $obed);
        self::assertSame($rizoto, $slot->portions[0]->chosenDishId);
        self::assertSame($rizoto, $slot->portions[0]->effectiveDishId);
    }

    /**
     * Změna jídla je změna nákupu, takže ji musí zachytit audit.
     *
     * @test
     */
    public function vyberJidlaZapiseAudit(): void
    {
        $obed = $this->vytvorJidlo('Oběd pátek', 2);
        $this->editor()->saveSlot($obed->getId(), [$this->jidlo('Guláš')], $this->rok);
        $zakaznik = $this->zakaznik();
        $porce = $this->kup($zakaznik, $obed);

        $this->chooser()->choose($zakaznik, $porce, $this->idJidla($obed, 'Guláš'), $this->rok, false);

        $zmeny = $this->connection()->fetchFirstColumn(
            "SELECT changes FROM shop_order_item_log WHERE order_item_id = :porce AND action = 'updated'",
            [
                'porce' => $porce,
            ],
        );
        self::assertCount(1, $zmeny);
        self::assertSame([null, $this->idJidla($obed, 'Guláš')], json_decode($zmeny[0], true)['meal_dish_id']);
    }

    /**
     * @test
     */
    public function prazdnyVyberVratiPorciKVychozimu(): void
    {
        $obed = $this->vytvorJidlo('Oběd pátek', 2);
        $this->editor()->saveSlot($obed->getId(), [$this->jidlo('Guláš'), $this->jidlo('Rizoto')], $this->rok);
        $zakaznik = $this->zakaznik();
        $porce = $this->kup($zakaznik, $obed);
        $this->chooser()->choose($zakaznik, $porce, $this->idJidla($obed, 'Rizoto'), $this->rok, false);

        $this->chooser()->choose($zakaznik, $porce, null, $this->rok, false);

        $slot = $this->slotMenu($this->reader()->forCustomer($zakaznik, $this->rok, false), $obed);
        self::assertNull($slot->portions[0]->chosenDishId);
        self::assertSame($this->idJidla($obed, 'Guláš'), $slot->portions[0]->effectiveDishId);
    }

    /**
     * @test
     */
    public function jidloJinehoSlotuKPorciNejdeVybrat(): void
    {
        $obed = $this->vytvorJidlo('Oběd pátek', 2);
        $vecere = $this->vytvorJidlo('Večeře pátek', 2);
        $this->editor()->saveSlot($obed->getId(), [$this->jidlo('Guláš')], $this->rok);
        $this->editor()->saveSlot($vecere->getId(), [$this->jidlo('Pizza')], $this->rok);
        $zakaznik = $this->zakaznik();
        $porce = $this->kup($zakaznik, $obed);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('~Oběd pátek~');

        $this->chooser()->choose($zakaznik, $porce, $this->idJidla($vecere, 'Pizza'), $this->rok, false);
    }

    /**
     * @test
     */
    public function ciziPorciNejdeVybrat(): void
    {
        $obed = $this->vytvorJidlo('Oběd pátek', 2);
        $this->editor()->saveSlot($obed->getId(), [$this->jidlo('Guláš')], $this->rok);
        $porceJineho = $this->kup($this->zakaznik(), $obed);

        $this->expectException(InvalidRequestException::class);

        $this->chooser()->choose($this->zakaznik(), $porceJineho, $this->idJidla($obed, 'Guláš'), $this->rok, false);
    }

    /**
     * Počty konkrétních jídel jdou do jídelny spolu s počty porcí, takže po konci prodeje jídla
     * už účastník nic nezmění. Pult ano — vyřizuje právě to, co online nejde.
     *
     * @test
     */
    public function poTerminuVybiraJenPult(): void
    {
        $obed = $this->vytvorJidlo('Oběd pátek', 2);
        $this->editor()->saveSlot($obed->getId(), [$this->jidlo('Guláš'), $this->jidlo('Rizoto')], $this->rok);
        $zakaznik = $this->zakaznik();
        $porce = $this->kup($zakaznik, $obed);
        $this->vyberUzavren = true;

        self::assertTrue($this->reader()->forCustomer($zakaznik, $this->rok, false)->locked);
        self::assertFalse($this->reader()->forCustomer($zakaznik, $this->rok, true)->locked);

        $this->chooser()->choose($zakaznik, $porce, $this->idJidla($obed, 'Rizoto'), $this->rok, true);
        self::assertSame($this->idJidla($obed, 'Rizoto'), $this->porce($porce)->getMealDish()?->getId());

        $this->expectException(NoLongerAvailableException::class);
        $this->chooser()->choose($zakaznik, $porce, $this->idJidla($obed, 'Guláš'), $this->rok, false);
    }

    /**
     * @test
     */
    public function predZverejnenimVybiraJenPult(): void
    {
        $obed = $this->vytvorJidlo('Oběd pátek', 2);
        $this->editor()->saveSlot($obed->getId(), [$this->jidlo('Guláš')], $this->rok);
        $zakaznik = $this->zakaznik();
        $porce = $this->kup($zakaznik, $obed);
        $this->zverejneno = false;

        $this->chooser()->choose($zakaznik, $porce, $this->idJidla($obed, 'Guláš'), $this->rok, true);

        $this->expectException(NoLongerAvailableException::class);
        $this->chooser()->choose($zakaznik, $porce, null, $this->rok, false);
    }

    /**
     * @test
     */
    public function editorUloziJidlaVPoradiAPrvniJeVychozi(): void
    {
        $obed = $this->vytvorJidlo('Oběd pátek', 2);

        $this->editor()->saveSlot($obed->getId(), [
            $this->jidlo('Guláš', popis: 'obsahuje lepek'),
            $this->jidlo('Rizoto', vegetarian: true),
        ], $this->rok);

        $jidla = $this->reader()->findSlot($obed->getId(), $this->rok)?->dishes ?? [];
        self::assertSame(['Guláš', 'Rizoto'], array_map(static fn (MealDish $dish): string => $dish->getName(), $jidla));
        self::assertSame([true, false], array_map(static fn (MealDish $dish): bool => $dish->isDefault(), $jidla));
        self::assertSame('obsahuje lepek', $jidla[0]->getDescription());
        self::assertTrue($jidla[1]->isVegetarian());
    }

    /**
     * @test
     */
    public function editorUpraviJidloNaMisteANezaloziNove(): void
    {
        $obed = $this->vytvorJidlo('Oběd pátek', 2);
        $this->editor()->saveSlot($obed->getId(), [$this->jidlo('Gulas')], $this->rok);
        $id = $this->idJidla($obed, 'Gulas');

        $this->editor()->saveSlot($obed->getId(), [$this->jidlo('Guláš', id: $id)], $this->rok);

        self::assertSame($id, $this->idJidla($obed, 'Guláš'));
    }

    /**
     * Smazané jídlo nesmí nechat porce viset na ničem: spadnou na výchozí jídlo.
     *
     * @test
     */
    public function smazaniVybranehoJidlaVratiPorceKVychozimu(): void
    {
        $obed = $this->vytvorJidlo('Oběd pátek', 2);
        $this->editor()->saveSlot($obed->getId(), [$this->jidlo('Guláš'), $this->jidlo('Rizoto')], $this->rok);
        $gulas = $this->idJidla($obed, 'Guláš');
        $zakaznik = $this->zakaznik();
        $porce = $this->kup($zakaznik, $obed);
        $this->chooser()->choose($zakaznik, $porce, $this->idJidla($obed, 'Rizoto'), $this->rok, false);

        $this->editor()->saveSlot($obed->getId(), [$this->jidlo('Guláš', id: $gulas)], $this->rok);

        $slot = $this->slotMenu($this->reader()->forCustomer($zakaznik, $this->rok, false), $obed);
        self::assertNull($slot->portions[0]->chosenDishId);
        self::assertSame($gulas, $slot->portions[0]->effectiveDishId);
    }

    /**
     * @test
     */
    public function dveVychoziJidlaEditorOdmitne(): void
    {
        $obed = $this->vytvorJidlo('Oběd pátek', 2);

        $this->expectException(InvalidRequestException::class);

        $this->editor()->saveSlot($obed->getId(), [$this->jidlo('Guláš', default: true), $this->jidlo('Rizoto', default: true)], $this->rok);
    }

    /**
     * @test
     */
    public function stejneJidloDvakratEditorOdmitne(): void
    {
        $obed = $this->vytvorJidlo('Oběd pátek', 2);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('~guláš~iu');

        $this->editor()->saveSlot($obed->getId(), [$this->jidlo('Guláš'), $this->jidlo(' guláš ')], $this->rok);
    }

    /**
     * Jídlo jiného slotu by se jinak přestěhovalo i s výběry lidí, kteří ho brali k jiné porci.
     *
     * @test
     */
    public function jidloJinehoSlotuEditorNepresune(): void
    {
        $obed = $this->vytvorJidlo('Oběd pátek', 2);
        $vecere = $this->vytvorJidlo('Večeře pátek', 2);
        $this->editor()->saveSlot($vecere->getId(), [$this->jidlo('Pizza')], $this->rok);

        $this->expectException(InvalidRequestException::class);

        $this->editor()->saveSlot($obed->getId(), [$this->jidlo('Pizza', id: $this->idJidla($vecere, 'Pizza'))], $this->rok);
    }

    /**
     * @test
     */
    public function kSnidaniSeJidlaNezadavaji(): void
    {
        $snidane = $this->vytvorJidlo('Snídaně pátek', 2, snidane: true);

        $this->expectException(InvalidRequestException::class);

        $this->editor()->saveSlot($snidane->getId(), [$this->jidlo('Vajíčka')], $this->rok);
    }

    /**
     * @test
     */
    public function adminVidiKolikPorciSiCoVybralo(): void
    {
        $obed = $this->vytvorJidlo('Oběd pátek', 2);
        $this->editor()->saveSlot($obed->getId(), [$this->jidlo('Guláš'), $this->jidlo('Rizoto')], $this->rok);
        $rizoto = $this->idJidla($obed, 'Rizoto');
        foreach ([$rizoto, $rizoto, null] as $vyber) {
            $zakaznik = $this->zakaznik(prihlasenNaGc: true);
            $porce = $this->kup($zakaznik, $obed);
            if ($vyber !== null) {
                $this->chooser()->choose($zakaznik, $porce, $vyber, $this->rok, false);
            }
        }
        // Nepřihlášený na GameCon se nepočítá, stejně jako v exportu pro jídelnu.
        $neprihlaseny = $this->zakaznik();
        $this->chooser()->choose($neprihlaseny, $this->kup($neprihlaseny, $obed), $rizoto, $this->rok, false);

        $slot = null;
        foreach ($this->reader()->forAdmin($this->rok)->slots as $kandidat) {
            if ($kandidat->variantId === $obed->getId()) {
                $slot = $kandidat;
            }
        }

        self::assertNotNull($slot);
        self::assertSame(3, $slot->portionCount);
        self::assertSame(1, $slot->unchosenCount);
        self::assertSame([0, 2], array_map(static fn ($dish): int => $dish->chosenCount, $slot->dishes));
    }

    private function schedule(): MealMenuSchedule
    {
        $test = $this;

        return new class($test) extends MealMenuSchedule {
            public function __construct(
                private readonly MealMenuTest $test,
            ) {
            }

            public function isPublished(): bool
            {
                return $this->test->jeZverejneno();
            }

            public function getPublishedFrom(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-07-02');
            }

            public function isChoiceClosed(): bool
            {
                return $this->test->jeVyberUzavren();
            }

            public function getChoiceDeadline(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-07-15');
            }
        };
    }

    public function jeZverejneno(): bool
    {
        return $this->zverejneno;
    }

    public function jeVyberUzavren(): bool
    {
        return $this->vyberUzavren;
    }

    private function reader(): MealMenuReader
    {
        $container = static::getContainer();

        return new MealMenuReader(
            $container->get(ProductRepository::class),
            $container->get(MealDishRepository::class),
            $container->get(OrderItemRepository::class),
            $this->schedule(),
            $this->connection(),
        );
    }

    private function chooser(): MealDishChooser
    {
        return new MealDishChooser(
            $this->entityManager(),
            static::getContainer()->get(OrderItemRepository::class),
            $this->reader(),
            $this->schedule(),
            ChybovePreklady::translator(),
        );
    }

    private function editor(): MealMenuEditor
    {
        return new MealMenuEditor(
            $this->entityManager(),
            static::getContainer()->get(OrderItemRepository::class),
            $this->reader(),
            ChybovePreklady::translator(),
        );
    }

    private function jidlo(string $nazev, ?int $id = null, bool $vegetarian = false, bool $default = false, ?string $popis = null): MealDishInputDto
    {
        $jidlo = new MealDishInputDto();
        $jidlo->id = $id;
        $jidlo->name = $nazev;
        $jidlo->vegetarian = $vegetarian;
        $jidlo->default = $default;
        $jidlo->description = $popis;

        return $jidlo;
    }

    private function idJidla(ProductVariant $porce, string $nazev): int
    {
        foreach ($this->reader()->findSlot($porce->getId(), $this->rok)?->dishes ?? [] as $jidlo) {
            if ($jidlo->getName() === $nazev) {
                return $jidlo->getId();
            }
        }

        self::fail("Jídlo {$nazev} v jídelníčku není.");
    }

    private function zakaznik(bool $prihlasenNaGc = false): User
    {
        /** @var User $zakaznik */
        $zakaznik = UserFactory::createOne([
            UserEntityStructure::login => 'stravnik_' . uniqid(),
            UserEntityStructure::email => 'stravnik_' . uniqid() . '@example.invalid',
        ])->_save()->_real();
        if ($prihlasenNaGc) {
            $this->connection()->executeStatement(
                'INSERT INTO uzivatele_role (id_uzivatele, id_role) VALUES (:uzivatel, :role)',
                [
                    'uzivatel' => $zakaznik->getId(),
                    'role'     => Role::PRIHLASEN_NA_LETOSNI_GC($this->rok),
                ],
            );
        }

        return $zakaznik;
    }

    /**
     * @return int id koupené porce
     */
    private function kup(User $zakaznik, ProductVariant $porce): int
    {
        static::getContainer()->get(MealWriter::class)->save($zakaznik, [$porce->getId()], $this->rok);

        $id = $this->connection()->fetchOne(
            'SELECT id_nakupu FROM shop_nakupy WHERE id_uzivatele = :zakaznik AND variant_id = :porce AND rok = :rok',
            [
                'zakaznik' => $zakaznik->getId(),
                'porce'    => $porce->getId(),
                'rok'      => $this->rok,
            ],
        );
        self::assertNotFalse($id);

        return (int) $id;
    }

    private function porce(int $id): OrderItem
    {
        $porce = $this->entityManager()->find(OrderItem::class, $id);
        self::assertNotNull($porce);
        $this->entityManager()->refresh($porce);

        return $porce;
    }

    private function vytvorJidlo(string $nazev, int $den, bool $snidane = false): ProductVariant
    {
        $kod = 'jidlo-' . uniqid();

        $produkt = new Product();
        $produkt->setName($nazev);
        $produkt->setCode($kod);
        $produkt->setCurrentPrice('140.00');
        $produkt->setDescription('');
        $produkt->setState(ProductStateEnum::PUBLIC);
        $produkt->setAccommodationDay($den);
        $produkt->addTag($this->tag(ProductTagCode::JIDLO));
        if ($snidane) {
            $produkt->addTag($this->tag(ProductTagCode::SNIDANE));
        }
        $this->entityManager()->persist($produkt);

        $varianta = new ProductVariant();
        $varianta->setProduct($produkt);
        $varianta->setCode($kod);
        $varianta->setPrice('140.00');
        $varianta->setPosition(0);
        $varianta->setAccommodationDay($den);
        $produkt->addVariant($varianta);
        $this->entityManager()->persist($varianta);
        $this->entityManager()->flush();

        return $varianta;
    }

    private function tag(ProductTagCode $kod): ProductTag
    {
        $this->connection()->executeStatement(
            'INSERT IGNORE INTO product_tag (code, name, created_at) VALUES (:code, :name, NOW())',
            [
                'code' => $kod->value,
                'name' => $kod->value,
            ],
        );

        $tag = $this->entityManager()->getRepository(ProductTag::class)->findOneBy([
            'code' => $kod->value,
        ]);
        self::assertNotNull($tag);

        return $tag;
    }

    /**
     * @param \App\Service\MealSlot[] $sloty
     * @param ProductVariant[]        $moje
     *
     * @return \App\Service\MealSlot[] jen sloty, které test založil — databáze je sdílená
     */
    private function mojeSloty(array $sloty, array $moje): array
    {
        $idcka = array_map(static fn (ProductVariant $varianta): ?int => $varianta->getId(), $moje);

        return array_values(array_filter($sloty, static fn ($slot): bool => in_array($slot->getVariantId(), $idcka, true)));
    }

    private function slotMenu(MealMenuOutputDto $menu, ProductVariant $porce): MealMenuSlotOutputDto
    {
        foreach ($menu->slots as $slot) {
            if ($slot->variantId === $porce->getId()) {
                return $slot;
            }
        }

        self::fail('Slot v jídelníčku chybí.');
    }
}
