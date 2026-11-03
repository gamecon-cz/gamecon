<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Aktivita\TypAktivity;
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
