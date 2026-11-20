<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Aktivita\HlavickaLinie;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Jazyk\AktualniJazyk;
use Gamecon\Jazyk\Jazyk;
use Gamecon\Tests\Db\AbstractTestDb;

class HlavickaLinieTest extends AbstractTestDb
{
    public function testSekceVJazyceJeAnglickaKdyzJiHlavickaMa(): void
    {
        $this->nastavHlavicku(TypAktivity::LARP, 'šéfka Larpů', 'head of larps');
        $hlavicka = HlavickaLinie::zTypu(TypAktivity::LARP);

        self::assertNotNull($hlavicka);
        self::assertSame('head of larps', $hlavicka->getSekceVJazyce(Jazyk::ANGLICTINA));
        self::assertSame('šéfka Larpů', $hlavicka->getSekceVJazyce(Jazyk::CESTINA));
        self::assertSame('Lenka „Cemi“ Zavadilová', $hlavicka->getJmeno());
        self::assertSame('larp@gamecon.cz', $hlavicka->getEmail());
    }

    public function testSekceVJazyceBezJazykuJeVJazyceStranky(): void
    {
        $this->nastavHlavicku(TypAktivity::LARP, 'šéfka Larpů', 'head of larps');
        $hlavicka = HlavickaLinie::zTypu(TypAktivity::LARP);

        self::assertNotNull($hlavicka);
        self::assertSame('šéfka Larpů', $hlavicka->getSekceVJazyce(), 'Mimo web (admin, cron) je jazyk čeština.');
        self::assertSame('head of larps', AktualniJazyk::provedVJazyce(Jazyk::ANGLICTINA, static fn () => $hlavicka->getSekceVJazyce()));
    }

    /**
     * @dataProvider provideNeprelozenaSekce
     */
    public function testSekceVJazyceBezPrekladuJeCeska(?string $sekceEn): void
    {
        $this->nastavHlavicku(TypAktivity::LARP, 'šéfka Larpů', $sekceEn);

        self::assertSame('šéfka Larpů', HlavickaLinie::zTypu(TypAktivity::LARP)?->getSekceVJazyce(Jazyk::ANGLICTINA));
    }

    /**
     * @return array<string, array{0: string|null}>
     */
    public static function provideNeprelozenaSekce(): array
    {
        return [
            'NULL'       => [null],
            'prázdná'    => [''],
            'jen mezery' => ['  '],
        ];
    }

    public function testLinieBezHlavickyHlavickuNema(): void
    {
        dbQuery('DELETE FROM akce_typy_hlavicky WHERE id_typu = $0', [
            0 => TypAktivity::LARP,
        ]);

        self::assertNull(HlavickaLinie::zTypu(TypAktivity::LARP));
    }

    public function testHlavickaBezVyplnenychTextuDavaPrazdneTexty(): void
    {
        $this->nastavHlavicku(TypAktivity::LARP, null, null, null, null);
        $hlavicka = HlavickaLinie::zTypu(TypAktivity::LARP);

        self::assertNotNull($hlavicka);
        self::assertSame('', $hlavicka->getSekceVJazyce(Jazyk::ANGLICTINA), 'Stránka linie pak ukáže zástupný text z katalogu.');
        self::assertSame('', $hlavicka->getJmeno());
        self::assertSame('', $hlavicka->getEmail());
    }

    private function nastavHlavicku(
        int $idTypu,
        ?string $sekce,
        ?string $sekceEn,
        ?string $jmeno = 'Lenka „Cemi“ Zavadilová',
        ?string $email = 'larp@gamecon.cz',
    ): void {
        dbQuery(<<<'SQL'
INSERT INTO akce_typy_hlavicky (id_typu, sekce, sekce_en, jmeno, email)
VALUES ($0, $1, $2, $3, $4)
ON DUPLICATE KEY UPDATE sekce = VALUES(sekce), sekce_en = VALUES(sekce_en), jmeno = VALUES(jmeno), email = VALUES(email)
SQL,
            [
                0 => $idTypu,
                1 => $sekce,
                2 => $sekceEn,
                3 => $jmeno,
                4 => $email,
            ],
        );
    }
}
