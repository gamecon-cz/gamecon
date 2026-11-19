<?php

declare(strict_types=1);

namespace Gamecon\Tests\Admin\Modules\Aktivity\Import\Activities;

use Gamecon\Admin\Modules\Aktivity\Import\Activities\ImportObjectsContainer;
use Gamecon\Admin\Modules\Aktivity\Import\Activities\ImportUsersCache;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Tests\Db\AbstractTestDb;

class ImportObjectsContainerTest extends AbstractTestDb
{
    /**
     * @dataProvider provideZapisyBezTypu
     */
    public function testBezTypuNeniProgramovaLinie(string $zapis): void
    {
        self::assertNull((new ImportObjectsContainer(new ImportUsersCache()))->getProgramLineFromValue($zapis));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideZapisyBezTypu(): array
    {
        return [
            'id'     => [(string) TypAktivity::SYSTEMOVA],
            'název'  => ['(bez typu – organizační)'],
            'adresa' => ['organizacni'],
        ];
    }

    public function testProgramovaLinieSeNajdeDleNazvu(): void
    {
        self::assertSame(
            TypAktivity::LARP,
            (new ImportObjectsContainer(new ImportUsersCache()))->getProgramLineFromValue('larpy')?->id(),
        );
    }
}
