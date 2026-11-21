<?php

declare(strict_types=1);

namespace Gamecon\Tests\Symfony\Entity;

use App\Entity\Activity;
use App\Entity\User;
use Gamecon\Aktivita\JazykAktivity;
use Gamecon\Jazyk\Jazyk;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Entita pustí do sloupce `jazyk` jen přesnou hodnotu enumu, stejně jako CHECK v databázi.
 * Collation utf8mb4_czech_ci by jinak považovala 'EN' i 'en ' za 'en'.
 */
class JazykVEntitachTest extends TestCase
{
    public function testAktivitaPrijmeKazdyJazykAktivity(): void
    {
        $aktivita = new Activity();
        foreach (JazykAktivity::cases() as $jazyk) {
            self::assertSame($jazyk->value, $aktivita->setJazyk($jazyk->value)->getJazyk());
        }
    }

    public function testUzivatelPrijmeKazdyJazyk(): void
    {
        $uzivatel = new User();
        foreach (Jazyk::cases() as $jazyk) {
            self::assertSame($jazyk->value, $uzivatel->setJazyk($jazyk->value)->getJazyk());
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function neplatneJazyky(): array
    {
        return [
            'velká písmena'   => ['EN'],
            'mezera na konci' => ['en '],
            'neznámý'         => ['de'],
            'prázdný'         => [''],
        ];
    }

    #[DataProvider('neplatneJazyky')]
    public function testAktivitaOdmitneNeplatnyJazyk(string $jazyk): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Activity())->setJazyk($jazyk);
    }

    #[DataProvider('neplatneJazyky')]
    public function testUzivatelOdmitneNeplatnyJazyk(string $jazyk): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new User())->setJazyk($jazyk);
    }
}
