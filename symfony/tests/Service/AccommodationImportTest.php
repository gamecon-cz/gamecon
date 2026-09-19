<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Service\AccommodationImport;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use Gamecon\Tests\Factory\UserFactory;

/**
 * Import posílá id předmětů, `AccommodationWriter` chce id variant — u ubytování to není
 * totéž, protože rodičem variant je nedělní noc. Testy hlídají ten překlad a to, že pravidla
 * zapisovače (návaznost nocí, nejméně dvě) přes import pořád platí.
 */
class AccommodationImportTest extends AbstractDatabaseKernelTestCase
{
    private const ROK = 2026;

    private function import(): AccommodationImport
    {
        return static::getContainer()->get(AccommodationImport::class);
    }

    private function ucastnik(): User
    {
        /** @var User $ucastnik */
        $ucastnik = UserFactory::createOne([
            UserEntityStructure::login => 'import_' . uniqid(),
            UserEntityStructure::email => 'import_' . uniqid() . '@example.invalid',
        ])->_save()->_real();

        return $ucastnik;
    }

    private function tagUbytovani(): ProductTag
    {
        $this->connection()->executeStatement(
            'INSERT IGNORE INTO product_tag (code, name, created_at) VALUES (:code, :code, NOW())',
            [
                'code' => ProductTagCode::UBYTOVANI->value,
            ],
        );

        $tag = $this->entityManager()
            ->getRepository(ProductTag::class)
            ->findOneBy([
                'code' => ProductTagCode::UBYTOVANI->value,
            ]);
        self::assertNotNull($tag);

        return $tag;
    }

    /**
     * Noc daného dne. Varianta nese týž kód jako produkt — tak to dělá migrace den-variant
     * a právě na tom stojí překlad id v importu.
     *
     * @return array{0: int, 1: int} id předmětu (legacy) a id varianty
     */
    private function vytvorNoc(int $den): array
    {
        $kod = 'noc-' . $den . '-' . uniqid();

        $produkt = new Product();
        $produkt->setName('Postel na 2L koleji den ' . $den);
        $produkt->setCode($kod);
        $produkt->setCurrentPrice('400.00');
        $produkt->setDescription('');
        $produkt->setState(ProductStateEnum::PUBLIC);
        $produkt->setAccommodationDay($den);
        $produkt->setProducedQuantity(10);
        $produkt->addTag($this->tagUbytovani());
        $this->entityManager()->persist($produkt);
        $this->entityManager()->flush();

        $varianta = new ProductVariant();
        $varianta->setProduct($produkt);
        $varianta->setName('den ' . $den);
        $varianta->setCode($kod);
        // Den musí sedět i na variantě — zapisovač podle něj pozná, že je to noc.
        $varianta->setAccommodationDay($den);
        $varianta->setPrice('400.00');
        $varianta->setPosition(0);
        $varianta->setRemainingQuantity(10);
        $produkt->addVariant($varianta);
        $this->entityManager()->persist($varianta);
        $this->entityManager()->flush();

        return [(int) $produkt->getId(), (int) $varianta->getId()];
    }

    private function pocetNoci(User $ucastnik): int
    {
        return (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM shop_nakupy WHERE id_uzivatele = :u AND rok = :rok',
            [
                'u'   => $ucastnik->getId(),
                'rok' => self::ROK,
            ],
        );
    }

    /**
     * @test
     */
    public function zapiseNociPodleIdPredmetu(): void
    {
        $ucastnik = $this->ucastnik();
        [$prvni] = $this->vytvorNoc(0);
        [$druha] = $this->vytvorNoc(1);

        $this->import()->ulozNociUcastnika($ucastnik->getId(), [$prvni, $druha], self::ROK, false);

        self::assertSame(2, $this->pocetNoci($ucastnik));
    }

    /**
     * Prázdný seznam = účastník nemá nic. Import tím maže noci lidem, kteří v souboru zbyli
     * bez pokoje.
     *
     * @test
     */
    public function prazdnySeznamNociSmazeCoUcastnikMel(): void
    {
        $ucastnik = $this->ucastnik();
        [$prvni] = $this->vytvorNoc(0);
        [$druha] = $this->vytvorNoc(1);
        $this->import()->ulozNociUcastnika($ucastnik->getId(), [$prvni, $druha], self::ROK, false);

        $this->import()->ulozNociUcastnika($ucastnik->getId(), [], self::ROK, false);

        self::assertSame(0, $this->pocetNoci($ucastnik));
    }

    /**
     * Pravidla zapisovače platí i přes import — jedna noc bez práva neprojde.
     *
     * @test
     */
    public function jednaNocBezPravaNeprojde(): void
    {
        $ucastnik = $this->ucastnik();
        [$jedina] = $this->vytvorNoc(0);

        $this->expectException(\RuntimeException::class);

        $this->import()->ulozNociUcastnika($ucastnik->getId(), [$jedina], self::ROK, false);
    }

    /**
     * @test
     */
    public function jednaNocSPravemProjde(): void
    {
        $ucastnik = $this->ucastnik();
        [$jedina] = $this->vytvorNoc(0);

        $this->import()->ulozNociUcastnika($ucastnik->getId(), [$jedina], self::ROK, povolitJednuNoc: true);

        self::assertSame(1, $this->pocetNoci($ucastnik));
    }

    /**
     * @test
     */
    public function nenavazujiciNociNeprojdou(): void
    {
        $ucastnik = $this->ucastnik();
        [$streda] = $this->vytvorNoc(0);
        [$sobota] = $this->vytvorNoc(3);

        $this->expectException(\RuntimeException::class);

        $this->import()->ulozNociUcastnika($ucastnik->getId(), [$streda, $sobota], self::ROK, false);
    }

    /**
     * @test
     */
    public function spolubydliciSeUlozi(): void
    {
        $ucastnik = $this->ucastnik();
        [$prvni] = $this->vytvorNoc(0);
        [$druha] = $this->vytvorNoc(1);

        $this->import()->ulozNociUcastnika(
            $ucastnik->getId(),
            [$prvni, $druha],
            self::ROK,
            false,
            'Pepa z Depa',
        );

        self::assertSame(
            'Pepa z Depa',
            $this->connection()->fetchOne(
                'SELECT ubytovan_s FROM uzivatele_hodnoty WHERE id_uzivatele = :u',
                [
                    'u' => $ucastnik->getId(),
                ],
            ),
        );
    }

    /**
     * Import hlásí „Změněno N záznamů" — musí tedy poznat, že podruhé už se nic nezměnilo.
     * Legacy to vracelo jako počet zapsaných řádků, tady stačí ano/ne.
     *
     * @test
     */
    public function opakovanyZapisTychzNociNehlasiZmenu(): void
    {
        $ucastnik = $this->ucastnik();
        [$prvni] = $this->vytvorNoc(0);
        [$druha] = $this->vytvorNoc(1);

        $poprve = $this->import()->ulozNociUcastnika($ucastnik->getId(), [$prvni, $druha], self::ROK, false);
        $podruhe = $this->import()->ulozNociUcastnika($ucastnik->getId(), [$prvni, $druha], self::ROK, false);

        self::assertTrue($poprve, 'První zápis mění stav');
        self::assertFalse($podruhe, 'Druhý zápis už nemá co měnit');
    }

    /**
     * Neexistující účastník je chyba řádku, ne pád importu — proto `RuntimeException`,
     * kterou si import překládá na `Chyba`.
     *
     * @test
     */
    public function neexistujiciUcastnikJeChyba(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->import()->ulozNociUcastnika(-1, [], self::ROK, false);
    }
}
