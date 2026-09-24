<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Service\MealWriter;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use Gamecon\Tests\Factory\UserFactory;

/**
 * Pult píše jídlo mimo košík, přímo SQL, takže kontroly z `CartService` na něj nesahají.
 * Stažený produkt ale nesmí prodat nikdo — jinak by admin endpoint zapsal nákup položky,
 * která v nabídce dávno není.
 */
class MealWriterAvailabilityTest extends AbstractDatabaseKernelTestCase
{
    private const ROK = 2026;

    private function writer(): MealWriter
    {
        return static::getContainer()->get(MealWriter::class);
    }

    private function zakaznik(): User
    {
        /** @var User $zakaznik */
        $zakaznik = UserFactory::createOne([
            UserEntityStructure::login => 'stravnik_' . uniqid(),
            UserEntityStructure::email => 'stravnik_' . uniqid() . '@example.invalid',
        ])->_save()->_real();

        return $zakaznik;
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

        $tag = $this->entityManager()
            ->getRepository(ProductTag::class)
            ->findOneBy([
                'code' => $kod->value,
            ]);
        self::assertNotNull($tag);

        return $tag;
    }

    private function vytvorJidlo(ProductStateEnum $stav, ?string $nabizetDo = null): ProductVariant
    {
        $kod = 'obed-' . uniqid();

        $produkt = new Product();
        $produkt->setName('Oběd čtvrtek');
        $produkt->setCode($kod);
        $produkt->setCurrentPrice('140.00');
        $produkt->setDescription('');
        $produkt->setState($stav);
        $produkt->setAccommodationDay(1);
        $produkt->setProducedQuantity(50);
        $produkt->addTag($this->tag(ProductTagCode::JIDLO));
        if ($nabizetDo !== null) {
            $produkt->setAvailableUntil(new \DateTimeImmutable($nabizetDo));
        }
        $this->entityManager()->persist($produkt);
        $this->entityManager()->flush();

        $varianta = new ProductVariant();
        $varianta->setProduct($produkt);
        $varianta->setName('porce');
        $varianta->setCode($kod);
        $varianta->setPrice('140.00');
        $varianta->setPosition(0);
        $varianta->setRemainingQuantity(50);
        $produkt->addVariant($varianta);
        $this->entityManager()->persist($varianta);
        $this->entityManager()->flush();

        return $varianta;
    }

    private function pocetNakupu(User $zakaznik): int
    {
        return (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM shop_nakupy WHERE id_uzivatele = :uzivatel AND rok = :rok',
            [
                'uzivatel' => $zakaznik->getId(),
                'rok'      => self::ROK,
            ],
        );
    }

    /**
     * Kapacita se musí hlídat i na admin cestě, ne jen v košíku — pult jinak prodá porci,
     * kterou kuchyně nemá.
     *
     * @test
     */
    public function vyprodaneJidloPultOdmitne(): void
    {
        $zakaznik = $this->zakaznik();
        $varianta = $this->vytvorJidlo(ProductStateEnum::PUBLIC);
        $this->vyprodej($varianta);

        $chyba = null;

        try {
            $this->writer()->save($zakaznik, [$varianta->getId()], self::ROK);
        } catch (\Throwable $zachycena) {
            $chyba = $zachycena;
        }

        self::assertNotNull($chyba, 'Vyprodané jídlo nesmí projít');
        self::assertSame(0, $this->pocetNakupu($zakaznik), 'Odmítnutý zápis nesmí nic zapsat');
    }

    /**
     * Sníží zásobu na nulu v obou zdrojích, protože se dnes čtou oba: admin cesta počítá
     * proti `kusu_vyrobeno`, košíková proti `remaining_quantity`.
     */
    private function vyprodej(ProductVariant $varianta): void
    {
        $this->connection()->executeStatement(
            'UPDATE product_variant SET remaining_quantity = 0 WHERE id = :id',
            [
                'id' => $varianta->getId(),
            ],
        );
        $this->connection()->executeStatement(
            'UPDATE shop_predmety SET kusu_vyrobeno = 0 WHERE id_predmetu = :id',
            [
                'id' => $varianta->getProduct()->getId(),
            ],
        );
        // Jen varianta a produkt: `clear()` by odpojil i zákazníka, kterého test drží.
        $this->entityManager()->refresh($varianta);
        $this->entityManager()->refresh($varianta->getProduct());
    }

    /**
     * @test
     */
    public function nabizeneJidloPultZapise(): void
    {
        $zakaznik = $this->zakaznik();
        $varianta = $this->vytvorJidlo(ProductStateEnum::PUBLIC);

        $this->writer()->save($zakaznik, [$varianta->getId()], self::ROK);

        self::assertSame(1, $this->pocetNakupu($zakaznik));
    }

    /**
     * Propadlé `nabizet_do` je konec samoobsluhy, ne stažení — legacy pultu prodej povoluje
     * přes `jidloBezZamku`, takže zápis projít musí.
     *
     * @test
     */
    public function jidloPoVlastnimTerminuPultZapiseDal(): void
    {
        $zakaznik = $this->zakaznik();
        $varianta = $this->vytvorJidlo(ProductStateEnum::PUBLIC, '2000-01-01 00:00:00');

        $this->writer()->save($zakaznik, [$varianta->getId()], self::ROK);

        self::assertSame(1, $this->pocetNakupu($zakaznik));
    }

    /**
     * Pult posílá vždy celý výběr, takže už koupené jídlo je v každém dalším uložení.
     * Když se mezitím stáhne z prodeje, nesmí to zablokovat ostatní změny — jinak pult
     * u toho zákazníka neuloží vůbec nic a odškrtnout to nejde (zamčená buňka).
     *
     * @test
     */
    public function stazeneJidloKtereZakaznikMaNeblokujeUlozeni(): void
    {
        $zakaznik = $this->zakaznik();
        $drzene = $this->vytvorJidlo(ProductStateEnum::PUBLIC);
        $this->writer()->save($zakaznik, [$drzene->getId()], self::ROK);

        // Admin ho po nákupu stáhne z prodeje. Přes SQL a s vyprázdněnou identity map,
        // aby `findByTag()` četl nový stav a ne ten z paměti.
        $this->connection()->executeStatement(
            'UPDATE shop_predmety SET stav = :stav WHERE id_predmetu = :id',
            [
                'stav' => ProductStateEnum::RETIRED->value,
                'id'   => $drzene->getProduct()->getId(),
            ],
        );
        $this->entityManager()->clear();

        $nove = $this->vytvorJidlo(ProductStateEnum::PUBLIC);
        $this->writer()->save($zakaznik, [$drzene->getId(), $nove->getId()], self::ROK);

        self::assertSame(2, $this->pocetNakupu($zakaznik), 'Držené jídlo nesmí blokovat přidání dalšího');
    }

    /**
     * @test
     */
    public function stazeneJidloNezapisaniAniPult(): void
    {
        $zakaznik = $this->zakaznik();
        $varianta = $this->vytvorJidlo(ProductStateEnum::RETIRED);

        $chyba = null;
        try {
            $this->writer()->save($zakaznik, [$varianta->getId()], self::ROK);
        } catch (\RuntimeException $zachycena) {
            $chyba = $zachycena;
        }

        self::assertNotNull($chyba, 'Stažené jídlo nesmí projít ani přes admin endpoint');
        self::assertSame(0, $this->pocetNakupu($zakaznik), 'Odmítnutý zápis nesmí nic zapsat');
    }
}
