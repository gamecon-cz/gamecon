<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Service\BreakfastCanceller;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use Gamecon\Tests\Factory\UserFactory;

/**
 * Snídaně v ceně hotelové noci se ruší; tyhle testy drží, co přesně to znamená, protože
 * `cancelCovered()` je nově jediná cesta, jak se ruší (legacy `zrusSnidaneProHotelovePokoje`
 * dělala totéž vlastním dotazem).
 */
class BreakfastCancellerTest extends AbstractDatabaseKernelTestCase
{
    private const ROK = 2026;

    private const STREDA = 0;

    private const CTVRTEK = 1;

    private const PATEK = 2;

    private const SOBOTA = 3;

    private function canceller(): BreakfastCanceller
    {
        return static::getContainer()->get(BreakfastCanceller::class);
    }

    private function ucastnik(): User
    {
        /** @var User $ucastnik */
        $ucastnik = UserFactory::createOne([
            UserEntityStructure::login => 'snidane_' . uniqid(),
            UserEntityStructure::email => 'snidane_' . uniqid() . '@example.invalid',
        ])->_save()->_real();

        return $ucastnik;
    }

    private function tag(ProductTagCode $kod): ProductTag
    {
        $this->connection()->executeStatement(
            'INSERT IGNORE INTO product_tag (code, name, created_at) VALUES (:code, :code, NOW())',
            [
                'code' => $kod->value,
            ],
        );

        $tag = $this->entityManager()->getRepository(ProductTag::class)->findOneBy([
            'code' => $kod->value,
        ]);
        self::assertNotNull($tag);

        return $tag;
    }

    private function vytvorVariantu(
        string $nazev,
        int $den,
        ProductTagCode $kategorie,
        bool $snidaneVCene = false,
    ): ProductVariant {
        $kod = strtolower(str_replace(' ', '_', $nazev)) . '_' . uniqid();

        $produkt = new Product();
        $produkt->setName($nazev);
        $produkt->setCode($kod);
        $produkt->setCurrentPrice('500.00');
        $produkt->setDescription('');
        $produkt->setState(ProductStateEnum::PUBLIC);
        $produkt->setAccommodationDay($den);
        $produkt->setBreakfastIncluded($snidaneVCene);
        $produkt->addTag($this->tag($kategorie));
        $this->entityManager()->persist($produkt);
        $this->entityManager()->flush();

        $varianta = new ProductVariant();
        $varianta->setProduct($produkt);
        $varianta->setName($nazev);
        $varianta->setCode($kod);
        $varianta->setCapacity(10);
        $varianta->setAccommodationDay($den);
        $varianta->setPrice('500.00');
        $varianta->setPosition(0);
        $produkt->addVariant($varianta);
        $this->entityManager()->persist($varianta);
        $this->entityManager()->flush();

        return $varianta;
    }

    private function objednej(User $ucastnik, ProductVariant $varianta): void
    {
        $this->connection()->executeStatement(
            'INSERT INTO shop_nakupy (id_uzivatele, variant_id, rok, cena_nakupni, datum)
             VALUES (:customer, :variant, :year, :cena, NOW())',
            [
                'customer' => $ucastnik->getId(),
                'variant'  => $varianta->getId(),
                'year'     => self::ROK,
                'cena'     => $varianta->getPrice(),
            ],
        );
    }

    private function pocetNakupu(User $ucastnik, ProductVariant $varianta): int
    {
        return (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM shop_nakupy
             WHERE id_uzivatele = :customer AND variant_id = :variant AND rok = :year',
            [
                'customer' => $ucastnik->getId(),
                'variant'  => $varianta->getId(),
                'year'     => self::ROK,
            ],
        );
    }

    public function testBreakfastCoveredByAHotelNightIsCancelled(): void
    {
        $ucastnik = $this->ucastnik();
        $hotel = $this->vytvorVariantu('Dvojlůžák čtvrtek', self::CTVRTEK, ProductTagCode::UBYTOVANI, snidaneVCene: true);
        $snidane = $this->vytvorVariantu('Snídaně pátek', self::PATEK, ProductTagCode::JIDLO);
        $this->objednej($ucastnik, $hotel);
        $this->objednej($ucastnik, $snidane);

        $zrusene = $this->canceller()->cancelCovered($ucastnik, self::ROK);

        self::assertSame([$snidane->getId()], $zrusene);
        self::assertSame(0, $this->pocetNakupu($ucastnik, $snidane));
    }

    /**
     * The caller locked the breakfasts it saw before the save; one bought after that is not
     * cancelled unlocked, it waits for the next save.
     */
    public function testBreakfastOutsideTheLockedVariantsIsKept(): void
    {
        $ucastnik = $this->ucastnik();
        $hotel = $this->vytvorVariantu('Dvojlůžák čtvrtek', self::CTVRTEK, ProductTagCode::UBYTOVANI, snidaneVCene: true);
        $snidane = $this->vytvorVariantu('Snídaně pátek', self::PATEK, ProductTagCode::JIDLO);
        $this->objednej($ucastnik, $hotel);
        $this->objednej($ucastnik, $snidane);

        $zrusene = $this->canceller()->cancelCovered($ucastnik, self::ROK, [(int) $hotel->getId()]);

        self::assertSame([], $zrusene);
        self::assertSame(1, $this->pocetNakupu($ucastnik, $snidane));
    }

    /**
     * Snídaně je v ceně jen u hotelu; spacák ani kolej ji nekryjí.
     */
    public function testBreakfastIsKeptForNonHotelAccommodation(): void
    {
        $ucastnik = $this->ucastnik();
        $spacak = $this->vytvorVariantu('Spacák čtvrtek', self::CTVRTEK, ProductTagCode::UBYTOVANI);
        $snidane = $this->vytvorVariantu('Snídaně pátek', self::PATEK, ProductTagCode::JIDLO);
        $this->objednej($ucastnik, $spacak);
        $this->objednej($ucastnik, $snidane);

        $zrusene = $this->canceller()->cancelCovered($ucastnik, self::ROK);

        self::assertSame([], $zrusene);
        self::assertSame(1, $this->pocetNakupu($ucastnik, $snidane));
    }

    /**
     * V ceně hotelu je jen snídaně, ne celá penze.
     */
    public function testLunchAndDinnerAreNeverCancelled(): void
    {
        $ucastnik = $this->ucastnik();
        $hotel = $this->vytvorVariantu('Dvojlůžák čtvrtek', self::CTVRTEK, ProductTagCode::UBYTOVANI, snidaneVCene: true);
        $obed = $this->vytvorVariantu('Oběd pátek', self::PATEK, ProductTagCode::JIDLO);
        $this->objednej($ucastnik, $hotel);
        $this->objednej($ucastnik, $obed);

        $zrusene = $this->canceller()->cancelCovered($ucastnik, self::ROK);

        self::assertSame([], $zrusene);
        self::assertSame(1, $this->pocetNakupu($ucastnik, $obed));
    }

    /**
     * Noc ze čtvrtka na pátek kryje páteční ráno, sobotní ne.
     */
    public function testOnlyTheMorningAfterAHotelNightIsCancelled(): void
    {
        $ucastnik = $this->ucastnik();
        $hotel = $this->vytvorVariantu('Dvojlůžák čtvrtek', self::CTVRTEK, ProductTagCode::UBYTOVANI, snidaneVCene: true);
        $snidanePatek = $this->vytvorVariantu('Snídaně pátek', self::PATEK, ProductTagCode::JIDLO);
        $snidaneSobota = $this->vytvorVariantu('Snídaně sobota', self::SOBOTA, ProductTagCode::JIDLO);
        $this->objednej($ucastnik, $hotel);
        $this->objednej($ucastnik, $snidanePatek);
        $this->objednej($ucastnik, $snidaneSobota);

        $this->canceller()->cancelCovered($ucastnik, self::ROK);

        self::assertSame(0, $this->pocetNakupu($ucastnik, $snidanePatek));
        self::assertSame(1, $this->pocetNakupu($ucastnik, $snidaneSobota), 'Sobotní ráno žádná hotelová noc nekryje');
    }

    public function testNothingIsCancelledWithoutAHotelNight(): void
    {
        $ucastnik = $this->ucastnik();
        $snidane = $this->vytvorVariantu('Snídaně pátek', self::PATEK, ProductTagCode::JIDLO);
        $this->objednej($ucastnik, $snidane);

        $zrusene = $this->canceller()->cancelCovered($ucastnik, self::ROK);

        self::assertSame([], $zrusene);
        self::assertSame(1, $this->pocetNakupu($ucastnik, $snidane));
    }

    /**
     * Středeční noc kryje čtvrteční ráno — ať je vidět, že se den odvozuje a nejde o pevné
     * „pátek".
     */
    public function testTheCoveredMorningFollowsTheNight(): void
    {
        $ucastnik = $this->ucastnik();
        $hotel = $this->vytvorVariantu('Dvojlůžák středa', self::STREDA, ProductTagCode::UBYTOVANI, snidaneVCene: true);
        $snidane = $this->vytvorVariantu('Snídaně čtvrtek', self::CTVRTEK, ProductTagCode::JIDLO);
        $this->objednej($ucastnik, $hotel);
        $this->objednej($ucastnik, $snidane);

        $zrusene = $this->canceller()->cancelCovered($ucastnik, self::ROK);

        self::assertSame([$snidane->getId()], $zrusene);
        self::assertSame(0, $this->pocetNakupu($ucastnik, $snidane));
    }

    public function testCoveredBreakfastIsTheOneOfTheMorningAfterTheNight(): void
    {
        $hotel = $this->vytvorVariantu('Dvojlůžák čtvrtek', self::CTVRTEK, ProductTagCode::UBYTOVANI, snidaneVCene: true);
        $snidanePatek = $this->vytvorVariantu('Snídaně pátek', self::PATEK, ProductTagCode::JIDLO);
        $snidaneSobota = $this->vytvorVariantu('Snídaně sobota', self::SOBOTA, ProductTagCode::JIDLO);
        $obed = $this->vytvorVariantu('Oběd pátek', self::PATEK, ProductTagCode::JIDLO);

        $kryte = $this->canceller()->coveredBreakfastVariants([(int) $hotel->getId()]);

        self::assertContains($snidanePatek->getId(), $kryte);
        self::assertNotContains($snidaneSobota->getId(), $kryte, 'Sobotní ráno noc ze čtvrtka nekryje');
        self::assertNotContains($obed->getId(), $kryte, 'Oběd není snídaně');
    }

    /**
     * The meal desk still sells past `nabizet_do` and sells suspended food, so a purchase in
     * flight can target a breakfast the public offer no longer lists.
     */
    public function testCoveredBreakfastsIncludeThoseOnlyTheDeskStillSells(): void
    {
        $hotel = $this->vytvorVariantu('Dvojlůžák čtvrtek', self::CTVRTEK, ProductTagCode::UBYTOVANI, snidaneVCene: true);
        $nabizena = $this->vytvorVariantu('Snídaně pátek', self::PATEK, ProductTagCode::JIDLO);
        $podPultem = $this->vytvorVariantu('Snídaně pátek podpultová', self::PATEK, ProductTagCode::JIDLO);
        $podPultem->getProduct()->setState(ProductStateEnum::SUSPENDED);
        $poTerminu = $this->vytvorVariantu('Snídaně pátek po termínu', self::PATEK, ProductTagCode::JIDLO);
        $poTerminu->getProduct()->setAvailableUntil(new \DateTimeImmutable('-1 day'));
        $this->entityManager()->flush();

        $kryte = $this->canceller()->coveredBreakfastVariants([(int) $hotel->getId()]);

        foreach ([$nabizena, $podPultem, $poTerminu] as $snidane) {
            self::assertContains($snidane->getId(), $kryte);
        }
    }

    public function testNoBreakfastIsCoveredByANightWithoutBreakfastInThePrice(): void
    {
        $spacak = $this->vytvorVariantu('Spacák čtvrtek', self::CTVRTEK, ProductTagCode::UBYTOVANI);
        $this->vytvorVariantu('Snídaně pátek', self::PATEK, ProductTagCode::JIDLO);

        self::assertSame([], $this->canceller()->coveredBreakfastVariants([(int) $spacak->getId()]));
    }

    public function testNoBreakfastIsCoveredWithoutNights(): void
    {
        $this->vytvorVariantu('Snídaně pátek', self::PATEK, ProductTagCode::JIDLO);

        self::assertSame([], $this->canceller()->coveredBreakfastVariants([]));
    }
}
