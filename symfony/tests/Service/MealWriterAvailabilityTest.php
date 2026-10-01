<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Exception\NoLongerAvailableException;
use App\Service\MealWriter;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use App\Tests\Support\SoubeznaTransakce;
use Doctrine\DBAL\ArrayParameterType;
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
        $varianta->setCapacity(50);
        $varianta->setPrice('140.00');
        $varianta->setPosition(0);
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

    private function vyprodej(ProductVariant $varianta): void
    {
        $this->connection()->executeStatement(
            'UPDATE product_variant SET capacity = 0 WHERE id = :id',
            [
                'id' => $varianta->getId(),
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
        } catch (NoLongerAvailableException $zachycena) {
            $chyba = $zachycena;
        }

        self::assertNotNull($chyba, 'Stažené jídlo nesmí projít ani přes admin endpoint');
        self::assertSame(0, $this->pocetNakupu($zakaznik), 'Odmítnutý zápis nesmí nic zapsat');
    }

    /**
     * Two desks swap two meals between two participants at the same moment. The other desk
     * holds both meals and then counts the one this desk gives up; were this desk to delete
     * that purchase before taking its locks, each would wait on the other.
     *
     * @test
     */
    public function vymenaJidelDvemaUcastnikumSeNezablokuje(): void
    {
        $vzdavane = $this->vytvorJidlo(ProductStateEnum::PUBLIC);
        $ziskavane = $this->vytvorJidlo(ProductStateEnum::PUBLIC);
        $prvni = $this->ucastnikVSql('vymena_prvni_');
        $druhy = $this->ucastnikVSql('vymena_druhy_');
        $this->nakup($prvni, $vzdavane);
        $nakupDruheho = $this->nakup($druhy, $ziskavane);
        $idVariant = [(int) $vzdavane->getId(), (int) $ziskavane->getId()];
        sort($idVariant);
        $this->connection()->commit();

        try {
            $souper = SoubeznaTransakce::spust($this->connection(), [
                ['sql', "SELECT id FROM product_variant WHERE id = {$idVariant[0]} FOR UPDATE"],
                ['sql', "SELECT id FROM product_variant WHERE id = {$idVariant[1]} FOR UPDATE"],
                ['hlasim', 'drzi obe jidla'],
                ['cekej', 700],
                ['sql', "DELETE FROM shop_nakupy WHERE id_nakupu = {$nakupDruheho}"],
                ['sql', 'SELECT COUNT(*) FROM shop_nakupy WHERE variant_id = ' . $vzdavane->getId() . ' AND rok = ' . self::ROK . ' LOCK IN SHARE MODE'],
                ['cekej', 300],
            ]);
            $chybaZapisu = null;
            try {
                $this->writer()->save($this->entityManager()->find(User::class, $prvni), [$ziskavane->getId()], self::ROK);
            } catch (\Throwable $chyba) {
                $chybaZapisu = $chyba;
            }

            self::assertSame('hotovo', $souper->dokonci());
            self::assertNull($chybaZapisu, (string) $chybaZapisu?->getMessage());
        } finally {
            $this->smazPotvrzeneJidlo([$prvni, $druhy], [$vzdavane, $ziskavane]);
            $this->connection()->beginTransaction();
        }
    }

    /**
     * The participant's cart buys the meal while the desk saves the same selection. The desk
     * waits for the cart's lock and must then see that meal as already held.
     *
     * @test
     */
    public function jidloKoupeneBehemCekaniNaZamekSeNekoupiZnovu(): void
    {
        $varianta = $this->vytvorJidlo(ProductStateEnum::PUBLIC);
        $ucastnik = $this->ucastnikVSql('soubezne_jidlo_');
        $this->connection()->commit();

        try {
            $souper = SoubeznaTransakce::spust($this->connection(), [
                ['sql', 'SELECT id FROM product_variant WHERE id = ' . $varianta->getId() . ' FOR UPDATE'],
                ['sql', 'INSERT INTO shop_nakupy (id_uzivatele, id_predmetu, variant_id, rok, cena_nakupni, datum)
                         VALUES (' . $ucastnik . ', ' . $varianta->getProduct()->getId() . ', ' . $varianta->getId() . ', ' . self::ROK . ', 140, NOW())'],
                ['hlasim', 'kupuje jidlo'],
                ['cekej', 500],
                ['potvrd', ''],
            ]);
            $this->writer()->save($this->entityManager()->find(User::class, $ucastnik), [$varianta->getId()], self::ROK);

            self::assertSame('hotovo', $souper->dokonci());
            self::assertSame(1, (int) $this->connection()->fetchOne(
                'SELECT COUNT(*) FROM shop_nakupy WHERE id_uzivatele = :uzivatel AND variant_id = :varianta',
                [
                    'uzivatel' => $ucastnik,
                    'varianta' => $varianta->getId(),
                ],
            ));
        } finally {
            $this->smazPotvrzeneJidlo([$ucastnik], [$varianta]);
            $this->connection()->beginTransaction();
        }
    }

    private function nakup(int $idUzivatele, ProductVariant $varianta): int
    {
        $this->connection()->executeStatement(
            'INSERT INTO shop_nakupy (id_uzivatele, id_predmetu, variant_id, rok, cena_nakupni, datum)
             VALUES (:uzivatel, :predmet, :varianta, :rok, 140, NOW())',
            [
                'uzivatel' => $idUzivatele,
                'predmet'  => $varianta->getProduct()->getId(),
                'varianta' => $varianta->getId(),
                'rok'      => self::ROK,
            ],
        );

        return (int) $this->connection()->lastInsertId();
    }

    /**
     * @param int[]            $idUzivatelu
     * @param ProductVariant[] $varianty
     */
    private function smazPotvrzeneJidlo(array $idUzivatelu, array $varianty): void
    {
        $spojeni = $this->connection();
        $uzivatele = [
            'uzivatele' => $idUzivatelu,
        ];
        $typ = [
            'uzivatele' => ArrayParameterType::INTEGER,
        ];
        $spojeni->executeStatement('DELETE FROM shop_nakupy WHERE id_uzivatele IN (:uzivatele)', $uzivatele, $typ);
        $spojeni->executeStatement('DELETE FROM shop_order WHERE customer_id IN (:uzivatele)', $uzivatele, $typ);
        foreach ($varianty as $varianta) {
            $spojeni->executeStatement('DELETE FROM product_variant WHERE id = :id', [
                'id' => $varianta->getId(),
            ]);
            $spojeni->executeStatement('DELETE FROM product_product_tag WHERE product_id = :id', [
                'id' => $varianta->getProduct()->getId(),
            ]);
            $spojeni->executeStatement('DELETE FROM shop_predmety WHERE id_predmetu = :id', [
                'id' => $varianta->getProduct()->getId(),
            ]);
        }
        $spojeni->executeStatement('DELETE FROM uzivatele_hodnoty WHERE id_uzivatele IN (:uzivatele)', $uzivatele, $typ);
    }
}
