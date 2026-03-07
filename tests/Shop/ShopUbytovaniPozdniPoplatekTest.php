<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use App\Entity\ShopItem;
use App\Structure\Entity\ShopItemEntityStructure;
use App\Structure\Entity\UserEntityStructure;
use Gamecon\Pravo;
use Gamecon\Shop\ShopUbytovani;
use Gamecon\Shop\StavPredmetu;
use Gamecon\Shop\TypPredmetu;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Tests\Factory\ShopItemFactory;
use Gamecon\Tests\Factory\UserFactory;

class ShopUbytovaniPozdniPoplatekTest extends AbstractTestDb
{
    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return true;
    }

    private function vytvorUbytovaniPredmet(int $den): ShopItem
    {
        $uniqueId = uniqid();

        return ShopItemFactory::createOne([
            ShopItemEntityStructure::nazev => 'Spacák ' . match ($den) {
                0 => 'středa',
                1 => 'čtvrtek',
                2 => 'pátek',
                3 => 'sobota',
            } . ' ' . $uniqueId,
            ShopItemEntityStructure::kodPredmetu  => 'UBYT_DEN' . $den . '_' . strtoupper($uniqueId),
            ShopItemEntityStructure::modelRok     => ROCNIK,
            ShopItemEntityStructure::cenaAktualni => '100',
            ShopItemEntityStructure::stav         => StavPredmetu::VEREJNY,
            ShopItemEntityStructure::kusuVyrobeno => 10,
            ShopItemEntityStructure::typ          => TypPredmetu::UBYTOVANI,
            ShopItemEntityStructure::ubytovaniDen => $den,
        ])->_real();
    }

    /**
     * @test
     */
    public function pozdniPoplatekSeUloziKNovemuNakupu(): void
    {
        $user = UserFactory::createOne([
            UserEntityStructure::login    => 'test_ubyt_' . uniqid(),
            UserEntityStructure::email    => 'test.ubyt.' . uniqid() . '@example.org',
            UserEntityStructure::jmeno    => 'Test',
            UserEntityStructure::prijmeni => 'Ubytovani',
        ])->_real();

        $predmet = $this->vytvorUbytovaniPredmet(1);
        $ucastnik = \Uzivatel::zIdUrcite($user->getId());

        ShopUbytovani::ulozObjednaneUbytovaniUcastnika(
            [$predmet->getId()],
            $ucastnik,
            false,
            ROCNIK,
            200.0,
        );

        $radek = dbOneLine(<<<SQL
SELECT cena_nakupni, poplatek, puvodni_cena
FROM shop_nakupy
WHERE id_uzivatele = $0 AND id_predmetu = $1 AND rok = $2
SQL,
            [$ucastnik->id(), $predmet->getId(), ROCNIK],
        );

        self::assertSame('200.00', $radek['poplatek']);
        self::assertSame('100.00', $radek['puvodni_cena']);
        self::assertSame('300.00', $radek['cena_nakupni']);
    }

    /**
     * @test
     */
    public function poplatekSeNeuloziKeStávajicimuNakupuJenKNovemu(): void
    {
        $user = UserFactory::createOne([
            UserEntityStructure::login    => 'test_ubyt_' . uniqid(),
            UserEntityStructure::email    => 'test.ubyt.' . uniqid() . '@example.org',
            UserEntityStructure::jmeno    => 'Test',
            UserEntityStructure::prijmeni => 'Ubytovani',
        ])->_real();

        $predmetCtvrtek = $this->vytvorUbytovaniPredmet(1);
        $predmetPatek = $this->vytvorUbytovaniPredmet(2);
        $predmetSobota = $this->vytvorUbytovaniPredmet(3);
        $ucastnik = \Uzivatel::zIdUrcite($user->getId());

        // Nejdřív objednat čtvrtek a pátek bez poplatku
        ShopUbytovani::ulozObjednaneUbytovaniUcastnika(
            [$predmetCtvrtek->getId(), $predmetPatek->getId()],
            $ucastnik,
            false,
            ROCNIK,
            0.0,
        );

        // Přidat sobotu s pozdním poplatkem (čtvrtek a pátek zůstávají)
        ShopUbytovani::ulozObjednaneUbytovaniUcastnika(
            [$predmetCtvrtek->getId(), $predmetPatek->getId(), $predmetSobota->getId()],
            $ucastnik,
            false,
            ROCNIK,
            200.0,
        );

        $nakupy = dbFetchAll(<<<SQL
SELECT id_predmetu, cena_nakupni, poplatek, puvodni_cena
FROM shop_nakupy
WHERE id_uzivatele = $0 AND rok = $1
ORDER BY id_predmetu
SQL,
            [$ucastnik->id(), ROCNIK],
        );

        self::assertCount(3, $nakupy);

        $nakupyPodleId = array_column($nakupy, null, 'id_predmetu');

        // Čtvrtek — původní nákup bez poplatku, nesmí být změněn
        $ctvrtek = $nakupyPodleId[$predmetCtvrtek->getId()];
        self::assertSame('0.00', $ctvrtek['poplatek'], 'Čtvrtek byl objednán dříve bez poplatku');
        self::assertSame('100.00', $ctvrtek['cena_nakupni'], 'Cena čtvrtku nesmí být ovlivněna pozdním poplatkem');

        // Pátek — původní nákup bez poplatku, nesmí být změněn
        $patek = $nakupyPodleId[$predmetPatek->getId()];
        self::assertSame('0.00', $patek['poplatek'], 'Pátek byl objednán dříve bez poplatku');
        self::assertSame('100.00', $patek['cena_nakupni'], 'Cena pátku nesmí být ovlivněna pozdním poplatkem');

        // Sobota — nový nákup s pozdním poplatkem
        $sobota = $nakupyPodleId[$predmetSobota->getId()];
        self::assertSame('200.00', $sobota['poplatek'], 'Sobota byla objednána s pozdním poplatkem');
        self::assertSame('100.00', $sobota['puvodni_cena'], 'Původní cena soboty je základní cena předmětu');
        self::assertSame('300.00', $sobota['cena_nakupni'], 'Cena soboty zahrnuje pozdní poplatek');
    }

    /**
     * @test
     */
    public function zmenaTypuUbytovaniZachovaPuvodniPoplatek(): void
    {
        $user = UserFactory::createOne([
            UserEntityStructure::login    => 'test_ubyt_' . uniqid(),
            UserEntityStructure::email    => 'test.ubyt.' . uniqid() . '@example.org',
            UserEntityStructure::jmeno    => 'Test',
            UserEntityStructure::prijmeni => 'Ubytovani',
        ])->_real();

        $dvojluzakCtvrtek = $this->vytvorUbytovaniPredmet(1);
        $trojluzakCtvrtek = $this->vytvorUbytovaniPredmet(1);
        $spacakPatek = $this->vytvorUbytovaniPredmet(2);
        $ucastnik = \Uzivatel::zIdUrcite($user->getId());

        // Původní objednávka čtvrtku s poplatkem 100 Kč
        ShopUbytovani::ulozObjednaneUbytovaniUcastnika(
            [$dvojluzakCtvrtek->getId()],
            $ucastnik,
            false,
            ROCNIK,
            100.0,
        );

        // Sazba poplatku se mezitím změnila na 200 Kč.
        // Uživatel změní typ ubytování ve čtvrtek a přidá pátek.
        // Čtvrtek musí zachovat původní poplatek 100 Kč, pátek dostane aktuální 200 Kč.
        ShopUbytovani::ulozObjednaneUbytovaniUcastnika(
            [$trojluzakCtvrtek->getId(), $spacakPatek->getId()],
            $ucastnik,
            false,
            ROCNIK,
            200.0,
        );

        $nakupy = dbFetchAll(<<<SQL
SELECT id_predmetu, cena_nakupni, poplatek, puvodni_cena
FROM shop_nakupy
WHERE id_uzivatele = $0 AND rok = $1
SQL,
            [$ucastnik->id(), ROCNIK],
        );

        self::assertCount(2, $nakupy, 'Má být dva nákupy – trojlůžák čtvrtek a spacák pátek');

        $nakupyPodleId = array_column($nakupy, null, 'id_predmetu');

        // Čtvrtek – typ změněn, ale poplatek musí zůstat původních 100 Kč
        $ctvrtek = $nakupyPodleId[$trojluzakCtvrtek->getId()];
        self::assertSame('100.00', $ctvrtek['poplatek'], 'Čtvrtek musí zachovat původní poplatek 100 Kč i po změně typu');
        self::assertSame('200.00', $ctvrtek['cena_nakupni'], 'Cena čtvrtku = základní cena + původní poplatek 100 Kč');

        // Pátek – nový den, dostane aktuální poplatek 200 Kč
        $patek = $nakupyPodleId[$spacakPatek->getId()];
        self::assertSame('200.00', $patek['poplatek'], 'Pátek jako nový den dostane aktuální poplatek 200 Kč');
        self::assertSame('300.00', $patek['cena_nakupni'], 'Cena pátku = základní cena + aktuální poplatek 200 Kč');
    }

    /**
     * @test
     */
    public function organizatorSUbytovanimZdarmaNeplatiPozdniPoplatek(): void
    {
        $user = UserFactory::createOne([
            UserEntityStructure::login    => 'test_ubyt_org_' . uniqid(),
            UserEntityStructure::email    => 'test.ubyt.org.' . uniqid() . '@example.org',
            UserEntityStructure::jmeno    => 'Test',
            UserEntityStructure::prijmeni => 'Organizator',
        ])->_real();

        $idUzivatele = $user->getId();

        // Vytvořit roli s právem UBYTOVANI_ZDARMA a přiřadit ji uživateli.
        // role_seznam nemá AUTO_INCREMENT, takže ID volíme ručně jako záporné číslo, abychom se nepřekrývali s reálnými daty.
        $idPrava = Pravo::UBYTOVANI_ZDARMA;
        $idRole = -999999;
        $kodRole = 'TEST_UBYT_ZDARMA_' . uniqid();
        dbQuery(<<<SQL
INSERT IGNORE INTO r_prava_soupis(id_prava, jmeno_prava, popis_prava)
VALUES ({$idPrava}, 'UBYTOVANI_ZDARMA', 'Má zdarma ubytování po celou dobu')
SQL,
        );
        dbQuery(
            "INSERT INTO role_seznam(id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role) VALUES ({$idRole}, '{$kodRole}', 'Test ubytování zdarma', '', -1, 'trvala', 'TEST_UBYT_ZDARMA')",
        );
        dbQuery(<<<SQL
INSERT INTO prava_role(id_role, id_prava) VALUES ({$idRole}, {$idPrava})
SQL,
        );
        dbQuery(<<<SQL
INSERT INTO uzivatele_role(id_uzivatele, id_role, posadil)
VALUES ({$idUzivatele}, {$idRole}, {$idUzivatele})
SQL,
        );

        $predmet = $this->vytvorUbytovaniPredmet(1);
        $ucastnik = \Uzivatel::zIdUrcite($idUzivatele);

        ShopUbytovani::ulozObjednaneUbytovaniUcastnika(
            [$predmet->getId()],
            $ucastnik,
            false,
            ROCNIK,
            200.0,
        );

        $radek = dbOneLine(<<<SQL
SELECT cena_nakupni, poplatek, puvodni_cena
FROM shop_nakupy
WHERE id_uzivatele = $0 AND id_predmetu = $1 AND rok = $2
SQL,
            [$ucastnik->id(), $predmet->getId(), ROCNIK],
        );

        self::assertSame('0.00', $radek['poplatek'], 'Organizátor s ubytováním zdarma nesmí platit pozdní poplatek');
        self::assertSame('100.00', $radek['puvodni_cena'], 'Původní cena předmětu se zachová');
        self::assertSame('100.00', $radek['cena_nakupni'], 'Celková cena nákupu bez poplatku');
    }
}
