<?php

declare(strict_types=1);

namespace Gamecon\Tests\HodnoceniTurnaje;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceHtml;
use Gamecon\Aktivita\TypAktivity;
use PHPUnit\Framework\TestCase;

/**
 * Vypravěč s Moje aktivity nevidí rám adminu, na hodnocení skupin ho proto vede odkaz z karty kola turnaje D&D.
 */
class OdkazVOnlinePrezenciTest extends TestCase
{
    public function testOdkazMaJenKoloTurnajeDnD(): void
    {
        self::assertSame(
            URL_ADMIN . '/hodnoceni-turnaje?aktivita=7623',
            OnlinePrezenceHtml::getUrlHodnoceniTurnaje($this->aktivita(7623, TypAktivity::DND, 1)),
        );
        self::assertNull(OnlinePrezenceHtml::getUrlHodnoceniTurnaje($this->aktivita(1, TypAktivity::LKD, 1)));
        self::assertNull(
            OnlinePrezenceHtml::getUrlHodnoceniTurnaje($this->aktivita(2, TypAktivity::DND, null)),
            'D&D mimo turnaj (třeba doprovodná hra) hodnocení nemá',
        );
    }

    private function aktivita(int $id, int $typ, ?int $kolo): Aktivita
    {
        $aktivita = $this->createPartialMock(Aktivita::class, ['id', 'typId', 'turnajKolo']);
        $aktivita->method('id')->willReturn($id);
        $aktivita->method('typId')->willReturn($typ);
        $aktivita->method('turnajKolo')->willReturn($kolo);

        return $aktivita;
    }
}
