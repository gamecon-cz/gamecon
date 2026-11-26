<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Aktivita\TypAktivity;
use Gamecon\Jazyk\AktualniJazyk;
use Gamecon\Jazyk\Jazyk;
use Gamecon\Tests\Db\AbstractTestDb;

class TypAktivityTest extends AbstractTestDb
{
    public function testNazevDlouhyVezmeNadpisZeStrankyLinie(): void
    {
        $this->priradLiniStranku(TypAktivity::LARP, "# Larpy na GameConu\n\nO larpech.");

        self::assertSame('Larpy na GameConu', TypAktivity::zId(TypAktivity::LARP)->nazevDlouhy());
    }

    public function testNazevDlouhyBezNadpisuNaStrankeLinieVratiNazevLinie(): void
    {
        $this->nastavNazevLinie(TypAktivity::LARP, 'Larpy na webu');
        $this->priradLiniStranku(TypAktivity::LARP, 'O larpech, ale bez nadpisu.');

        self::assertSame('Larpy na webu', TypAktivity::zId(TypAktivity::LARP)->nazevDlouhy());
    }

    public function testNazevLinieJakoNahradaZaNadpisJeEscapovanyProHtml(): void
    {
        $this->nastavNazevLinie(TypAktivity::LARP, 'Larpy & "hry"');
        $this->priradLiniStranku(TypAktivity::LARP, 'O larpech, ale bez nadpisu.');

        self::assertSame('Larpy &amp; &quot;hry&quot;', TypAktivity::zId(TypAktivity::LARP)->nazevDlouhy());
    }

    public function testNazevVJazyceJeAnglickyKdyzHoLinieMa(): void
    {
        $this->nastavNazvyLinie(TypAktivity::LARP, 'larpy', 'larps');
        $typ = TypAktivity::zId(TypAktivity::LARP);

        self::assertSame('larps', $typ->getNazevVJazyce(Jazyk::ANGLICTINA));
        self::assertSame('larpy', $typ->getNazevVJazyce(Jazyk::CESTINA));
        self::assertSame('larpy', $typ->nazev(), 'Český název je i klíč (import, program), zůstává český.');
    }

    public function testNazevVJazyceBezJazykuJeVJazyceStranky(): void
    {
        $this->nastavNazvyLinie(TypAktivity::LARP, 'larpy', 'larps');
        $typ = TypAktivity::zId(TypAktivity::LARP);

        self::assertSame('larpy', $typ->getNazevVJazyce(), 'Mimo web (admin, cron) je jazyk čeština.');
        self::assertSame('larps', AktualniJazyk::provedVJazyce(Jazyk::ANGLICTINA, static fn () => $typ->getNazevVJazyce()));
    }

    /**
     * @dataProvider provideNeprelozenyNazev
     */
    public function testNazevVJazyceBezPrekladuJeCesky(?string $anglickyNazev): void
    {
        $this->nastavNazvyLinie(TypAktivity::LARP, 'larpy', $anglickyNazev);

        self::assertSame('larpy', TypAktivity::zId(TypAktivity::LARP)->getNazevVJazyce(Jazyk::ANGLICTINA));
    }

    /**
     * @return array<string, array{0: string|null}>
     */
    public static function provideNeprelozenyNazev(): array
    {
        return [
            'NULL'       => [null],
            'prázdný'    => [''],
            'jen mezery' => ['   '],
        ];
    }

    public function testPopisKratkyVJazyceBezPrekladuJeCesky(): void
    {
        dbQuery(<<<'SQL'
UPDATE akce_typy SET popis_kratky = 'Český popis', popis_kratky_en = NULL WHERE id_typu = $1
SQL, [TypAktivity::LARP]);
        self::assertSame('Český popis', TypAktivity::zId(TypAktivity::LARP)->getPopisKratkyVJazyce(Jazyk::ANGLICTINA));

        dbQuery(<<<'SQL'
UPDATE akce_typy SET popis_kratky_en = 'English description' WHERE id_typu = $1
SQL, [TypAktivity::LARP]);
        self::assertSame('English description', TypAktivity::zId(TypAktivity::LARP)->getPopisKratkyVJazyce(Jazyk::ANGLICTINA));
    }

    private function nastavNazvyLinie(int $idTypu, string $cesky, ?string $anglicky): void
    {
        dbQuery(<<<'SQL'
UPDATE akce_typy SET typ_1pmn = $1, typ_1pmn_en = $2 WHERE id_typu = $3
SQL, [$cesky, $anglicky, $idTypu]);
    }

    private function nastavNazevLinie(int $idTypu, string $nazev): void
    {
        dbQuery(<<<'SQL'
UPDATE akce_typy SET typ_1pmn = $1 WHERE id_typu = $2
SQL, [$nazev, $idTypu]);
    }

    private function priradLiniStranku(int $idTypu, string $obsah): void
    {
        dbInsert('stranky', [
            'url_stranky' => 'test-linie-' . $idTypu,
            'obsah'       => $obsah,
            'poradi'      => 0,
        ]);
        dbQuery(<<<'SQL'
UPDATE akce_typy SET stranka_o = $1 WHERE id_typu = $2
SQL, [dbInsertId(), $idTypu]);
    }
}
