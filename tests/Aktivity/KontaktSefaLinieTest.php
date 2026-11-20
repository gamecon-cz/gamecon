<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Aktivita\KontaktSefaLinie;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Jazyk\AktualniJazyk;
use Gamecon\Jazyk\Jazyk;
use Gamecon\Tests\Db\AbstractTestDb;

class KontaktSefaLinieTest extends AbstractTestDb
{
    public function testSekceVJazyceJeAnglickaKdyzJiHlavickaMa(): void
    {
        $this->nastavHlavicku(TypAktivity::LARP, 'šéfka Larpů', 'head of larps');
        $kontakt = KontaktSefaLinie::zTypu(TypAktivity::LARP);

        self::assertSame('head of larps', $kontakt->getSekceVJazyce(Jazyk::ANGLICTINA));
        self::assertSame('šéfka Larpů', $kontakt->getSekceVJazyce(Jazyk::CESTINA));
        self::assertSame('šéfka Larpů', $kontakt->getSekce(), 'Do českých vět (upozornění na věk, e-mail) jde česká sekce.');
        self::assertSame('Lenka „Cemi“ Zavadilová', $kontakt->getJmeno());
        self::assertSame('larp@gamecon.cz', $kontakt->getEmail());
    }

    public function testSekceVJazyceBezJazykuJeVJazyceStranky(): void
    {
        $this->nastavHlavicku(TypAktivity::LARP, 'šéfka Larpů', 'head of larps');
        $kontakt = KontaktSefaLinie::zTypu(TypAktivity::LARP);

        self::assertSame('šéfka Larpů', $kontakt->getSekceVJazyce(), 'Mimo web (admin, cron) je jazyk čeština.');
        self::assertSame('head of larps', AktualniJazyk::provedVJazyce(Jazyk::ANGLICTINA, static fn () => $kontakt->getSekceVJazyce()));
    }

    /**
     * @dataProvider provideNeprelozenaSekce
     */
    public function testSekceVJazyceBezPrekladuJeCeska(?string $sekceEn): void
    {
        $this->nastavHlavicku(TypAktivity::LARP, 'šéfka Larpů', $sekceEn);

        self::assertSame('šéfka Larpů', KontaktSefaLinie::zTypu(TypAktivity::LARP)->getSekceVJazyce(Jazyk::ANGLICTINA));
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

    public function testLinieBezHlavickyNemaSekciAniJmeno(): void
    {
        dbQuery('DELETE FROM akce_typy_hlavicky WHERE id_typu = $0', [
            0 => TypAktivity::LARP,
        ]);
        KontaktSefaLinie::smazCache();
        $kontakt = KontaktSefaLinie::zTypu(TypAktivity::LARP);

        self::assertNull($kontakt->getSekceVJazyce(Jazyk::ANGLICTINA), 'Stránka linie pak ukáže zástupný text z katalogu.');
        self::assertNull($kontakt->getJmeno());
    }

    public function testHlavickaBezVyplnenychTextuNemaSekciAniJmeno(): void
    {
        $this->nastavHlavicku(TypAktivity::LARP, null, null, null, null);
        $kontakt = KontaktSefaLinie::zTypu(TypAktivity::LARP);

        self::assertNull($kontakt->getSekceVJazyce(Jazyk::ANGLICTINA), 'Stránka linie pak ukáže zástupný text z katalogu.');
        self::assertNull($kontakt->getJmeno());
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
        KontaktSefaLinie::smazCache();
    }
}
