<?php

declare(strict_types=1);

namespace App\Tests\Report;

use App\Entity\MealDish;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Service\MealWriter;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use Gamecon\Report\JidlaZJidelnicku;
use Gamecon\Role\Role;
use Gamecon\Tests\Factory\UserFactory;

/**
 * Exporty jídelníčku jedou legacy SQL, takže si výchozí jídlo dosazují samy. Musí vyjít stejně
 * jako u účastníka (MealSlot::getEffectiveDish()), jinak jídelna uvaří něco jiného, než lidé
 * v přihlášce vidí.
 */
class JidlaZJidelnickuTest extends AbstractDatabaseKernelTestCase
{
    /**
     * @test
     */
    public function poctyJsouZaKazdeJidloIBezVyberuIZadneObjednavky(): void
    {
        $obed = $this->vytvorJidlo('Oběd pátek', 2);
        $gulas = $this->jidlo($obed, 'Guláš', 0);
        $rizoto = $this->jidlo($obed, 'Rizoto', 1, vychozi: true, vegetarianske: true);
        $this->jidlo($obed, 'Svíčková', 2);
        $this->kupPorci($obed, $gulas);
        $this->kupPorci($obed, $rizoto);
        $this->kupPorci($obed, null);
        $this->kupPorci($obed, null, prihlasenNaGc: false);

        $radky = $this->mojeRadky((new JidlaZJidelnicku())->poctyJidel(ROCNIK), 'Oběd pátek');

        self::assertSame([
            [
                'den'                => 'pátek',
                'porce'              => 'Oběd pátek',
                'jidlo'              => 'Guláš',
                'vegetarianske'      => 'ne',
                'vybrali_si'         => 1,
                'bez_vyberu_vychozi' => 0,
                'celkem'             => 1,
            ],
            [
                'den'                => 'pátek',
                'porce'              => 'Oběd pátek',
                'jidlo'              => 'Rizoto',
                'vegetarianske'      => 'ano',
                'vybrali_si'         => 1,
                'bez_vyberu_vychozi' => 1,
                'celkem'             => 2,
            ],
            [
                'den'                => 'pátek',
                'porce'              => 'Oběd pátek',
                'jidlo'              => 'Svíčková',
                'vegetarianske'      => 'ne',
                'vybrali_si'         => 0,
                'bez_vyberu_vychozi' => 0,
                'celkem'             => 0,
            ],
        ], $radky, 'Nepřihlášený na GameCon se nepočítá, nevybraní spadnou na výchozí Rizoto');
    }

    /**
     * @test
     */
    public function porceBezJidelnickuMaRadekJenSPoctem(): void
    {
        $snidane = $this->vytvorJidlo('Snídaně pátek', 2, snidane: true);
        $this->kupPorci($snidane, null);
        $this->kupPorci($snidane, null);

        $radky = $this->mojeRadky((new JidlaZJidelnicku())->poctyJidel(ROCNIK), 'Snídaně pátek');

        self::assertSame([
            [
                'den'                => 'pátek',
                'porce'              => 'Snídaně pátek',
                'jidlo'              => '',
                'vegetarianske'      => '',
                'vybrali_si'         => '',
                'bez_vyberu_vychozi' => '',
                'celkem'             => 2,
            ],
        ], $radky);
    }

    /**
     * Výchozí jídlo bez označení je první v pořadí — stejně jako u účastníka.
     *
     * @test
     */
    public function bezOznacenehoVychozihoDostaneNevybranyPrvniJidlo(): void
    {
        $vecere = $this->vytvorJidlo('Večeře pátek', 2);
        $this->jidlo($vecere, 'Pizza', 1);
        $this->jidlo($vecere, 'Halušky', 0);
        $login = $this->kupPorci($vecere, null);

        $vyber = array_values(array_filter(
            (new JidlaZJidelnicku())->vyberUcastniku(ROCNIK),
            static fn (array $radek): bool => $radek['login_uzivatele'] === $login,
        ));

        self::assertCount(1, $vyber);
        self::assertSame('Halušky', $vyber[0]['jidlo']);
        self::assertSame('ne', $vyber[0]['vybral_si']);
        self::assertSame('pátek', $vyber[0]['den']);
    }

    /**
     * @test
     */
    public function vyberUcastnikuVynechaSnidane(): void
    {
        $snidane = $this->vytvorJidlo('Snídaně pátek', 2, snidane: true);
        $login = $this->kupPorci($snidane, null);

        $vyber = array_filter(
            (new JidlaZJidelnicku())->vyberUcastniku(ROCNIK),
            static fn (array $radek): bool => $radek['login_uzivatele'] === $login,
        );

        self::assertSame([], $vyber);
    }

    /**
     * @param list<array<string, string|int>> $radky
     *
     * @return list<array<string, string|int>>
     */
    private function mojeRadky(array $radky, string $porce): array
    {
        return array_values(array_filter($radky, static fn (array $radek): bool => $radek['porce'] === $porce));
    }

    /**
     * @return string login zákazníka
     */
    private function kupPorci(ProductVariant $porce, ?MealDish $jidlo, bool $prihlasenNaGc = true): string
    {
        $login = 'stravnik_' . uniqid();
        /** @var User $zakaznik */
        $zakaznik = UserFactory::createOne([
            UserEntityStructure::login => $login,
            UserEntityStructure::email => $login . '@example.invalid',
        ])->_save()->_real();
        if ($prihlasenNaGc) {
            $this->connection()->executeStatement(
                'INSERT INTO uzivatele_role (id_uzivatele, id_role) VALUES (:uzivatel, :role)',
                [
                    'uzivatel' => $zakaznik->getId(),
                    'role'     => Role::PRIHLASEN_NA_LETOSNI_GC,
                ],
            );
        }

        static::getContainer()->get(MealWriter::class)->save($zakaznik, [(int) $porce->getId()], ROCNIK);

        if ($jidlo !== null) {
            $nakup = $this->entityManager()->getRepository(OrderItem::class)->findOneBy([
                'customer' => $zakaznik,
                'variant'  => $porce,
            ]);
            self::assertNotNull($nakup);
            // MealWriter po uložení čistí entity manager, jídlo je odpojené
            $nakup->setMealDish($this->entityManager()->find(MealDish::class, $jidlo->getId()));
            $this->entityManager()->flush();
        }

        return $login;
    }

    private function jidlo(ProductVariant $porce, string $nazev, int $poradi, bool $vychozi = false, bool $vegetarianske = false): MealDish
    {
        $jidlo = new MealDish($porce, ROCNIK, $nazev);
        $jidlo->setPosition($poradi);
        $jidlo->setDefault($vychozi);
        $jidlo->setVegetarian($vegetarianske);
        $this->entityManager()->persist($jidlo);
        $this->entityManager()->flush();

        return $jidlo;
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
}
