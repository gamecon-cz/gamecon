<?php

declare(strict_types=1);

namespace Gamecon\Tests\Web\Motiv;

use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Web\Motiv\BarvaWebuEnum;
use Gamecon\Web\Motiv\BarvyWebu;
use Gamecon\Web\Motiv\BarvyWebuRepository;
use Gamecon\Web\Motiv\StavBarevWebuEnum;

class BarvyWebuRepositoryTest extends AbstractTestDb
{
    public function testBezUlozenychBarevJsouVychozi(): void
    {
        $repository = new BarvyWebuRepository();

        self::assertTrue($repository->zverejnene()->jsouVychozi());
        self::assertNull($repository->dej(StavBarevWebuEnum::NAVRH));
        self::assertNull($repository->dej(StavBarevWebuEnum::ZVEREJNENO));
    }

    public function testNavrhNavstevniciNevidi(): void
    {
        $repository = new BarvyWebuRepository();
        $repository->ulozNavrh(self::modra(), \Uzivatel::SYSTEM);

        self::assertTrue($repository->zverejnene()->jsouVychozi());
        $navrh = $repository->dej(StavBarevWebuEnum::NAVRH);
        self::assertNotNull($navrh);
        self::assertSame('#0057b8', $navrh->barvy->getHodnota(BarvaWebuEnum::CERVENA));
        self::assertSame(\Uzivatel::SYSTEM, $navrh->zmenil);
    }

    public function testZverejnenyNavrhVidiVsichniANavrhZmizi(): void
    {
        $repository = new BarvyWebuRepository();
        $repository->ulozNavrh(self::modra(), \Uzivatel::SYSTEM);

        self::assertTrue($repository->zverejniNavrh(\Uzivatel::SYSTEM));

        self::assertSame('#0057b8', $repository->zverejnene()->getHodnota(BarvaWebuEnum::CERVENA));
        self::assertNull($repository->dej(StavBarevWebuEnum::NAVRH));
    }

    public function testDalsiZverejneniPrepiseBarvy(): void
    {
        $repository = new BarvyWebuRepository();
        $repository->ulozNavrh(self::modra(), \Uzivatel::SYSTEM);
        $repository->zverejniNavrh(\Uzivatel::SYSTEM);

        $repository->ulozNavrh(BarvyWebu::vychozi(), \Uzivatel::SYSTEM);
        $repository->zverejniNavrh(\Uzivatel::SYSTEM);

        self::assertTrue($repository->zverejnene()->jsouVychozi());
    }

    public function testBezNavrhuNeniCoZverejnit(): void
    {
        self::assertFalse((new BarvyWebuRepository())->zverejniNavrh(\Uzivatel::SYSTEM));
    }

    public function testZahozenyNavrhNechaZverejneneBarvy(): void
    {
        $repository = new BarvyWebuRepository();
        $repository->ulozNavrh(self::modra(), \Uzivatel::SYSTEM);

        $repository->zahodNavrh();

        self::assertNull($repository->dej(StavBarevWebuEnum::NAVRH));
        self::assertTrue($repository->zverejnene()->jsouVychozi());
    }

    private static function modra(): BarvyWebu
    {
        return BarvyWebu::zPole([
            BarvaWebuEnum::CERVENA->value => '#0057b8',
        ]);
    }
}
