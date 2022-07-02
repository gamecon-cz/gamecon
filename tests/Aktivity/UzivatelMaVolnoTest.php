<?php

namespace Gamecon\Tests\Aktivity;

use Gamecon\Tests\Db\UzivatelDbTest;
use Gamecon\Aktivita\Aktivita;

class UzivatelMaVolnoTest extends UzivatelDbTest
{

    protected static $initData = '
    # akce_seznam
    id_akce, stav, typ, rok,     zacatek,          konec
    1,       1,    1,   ' . ROK . ', 2000-01-01 16:00, 2000-01-01 18:00
    2,       1,    1,   ' . ROK . ', 2000-01-01 10:00, 2000-01-01 12:00
';

    private static $uzivatel;

    static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        self::$uzivatel = self::prihlasenyUzivatel();
        Aktivita::zId(1)->prihlas(self::$uzivatel);
        Aktivita::zId(2)->prihlas(self::$uzivatel);
    }

    function testZadneAktivity() {
        self::assertTrue(
            self::prihlasenyUzivatel()->maVolno(
                new \DateTime('2000-01-01 00:00'),
                new \DateTime('2000-01-01 24:00')
            )
        );
    }

    /**
     * @dataProvider ruzneVarianty
     */
    public function testRuzneVarianty(string $od, string $do, ?int $aktivitaId, bool $ocekavanyVysledek) {
        self::assertSame(
            $ocekavanyVysledek,
            self::$uzivatel->maVolno(
                new \DateTime('2000-01-01 ' . $od),
                new \DateTime('2000-01-01 ' . $do),
                Aktivita::zId($aktivitaId)
            )
        );
    }

    public function ruzneVarianty(): array {
        return [
            'překrytí konce' => ['17:00', '19:00', null, false],
            'překrytí celé aktivity' => ['15:00', '19:00', null, false],
            'překrytí více aktivit' => ['08:00', '19:00', null, false],
            'těsně po konci' => ['18:00', '19:00', null, true],
            'těsně před začátkem' => ['15:00', '16:00', null, true],
            'překrytí ignorované aktivity' => ['15:00', '19:00', 1, true],
        ];
    }
}
