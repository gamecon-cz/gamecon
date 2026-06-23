<?php

declare(strict_types=1);

namespace Gamecon\Tests\Admin\Modules\Aktivity\Import\Activities;

use Gamecon\Admin\Modules\Aktivity\Import\Activities\Exceptions\DuplicatedUnifiedKeyException;
use Gamecon\Admin\Modules\Aktivity\Import\Activities\ImportKeyUnifier;
use PHPUnit\Framework\TestCase;

class ImportKeyUnifierTest extends TestCase
{
    public function testPlaceholderLokaceTvorenePomlckamiMajiRuzneKlice(): void
    {
        // Placeholder locations like '-' and '--' used to collapse to an empty
        // string at the alnum depth, so the second collided with the first.
        $klicProPomlcku = ImportKeyUnifier::toUnifiedKey('-', []);
        $klicProDvePomlcky = ImportKeyUnifier::toUnifiedKey('--', [$klicProPomlcku]);

        self::assertSame('-', $klicProPomlcku);
        self::assertSame('--', $klicProDvePomlcky);
        self::assertNotSame($klicProPomlcku, $klicProDvePomlcky);
    }

    public function testPodtrzitkaZustavajiSoucastiKlice(): void
    {
        self::assertSame('a_b', ImportKeyUnifier::toUnifiedKey('A _ B', []));
    }

    public function testRuznePlaceholderyNezpusobiVyjimkuPriImportu(): void
    {
        $obsazeneKlice = [];
        foreach (['-', '--', '---'] as $nazevLokace) {
            $obsazeneKlice[] = ImportKeyUnifier::toUnifiedKey($nazevLokace, $obsazeneKlice);
        }

        self::assertSame(['-', '--', '---'], $obsazeneKlice);
    }

    public function testStejnyPlaceholderDvakratStaleHlasiDuplicitu(): void
    {
        $prvni = ImportKeyUnifier::toUnifiedKey('-', []);

        $this->expectException(DuplicatedUnifiedKeyException::class);
        ImportKeyUnifier::toUnifiedKey('-', [$prvni]);
    }

    public function testZpravaVyjimkyUvadiSpravnouHloubkuAVyslednyKlic(): void
    {
        $prvni = ImportKeyUnifier::toUnifiedKey('-', [], ImportKeyUnifier::UNIFY_UP_TO_ALNUMS);

        try {
            ImportKeyUnifier::toUnifiedKey('-', [$prvni], ImportKeyUnifier::UNIFY_UP_TO_ALNUMS);
            self::fail('Ocekavana DuplicatedUnifiedKeyException nebyla vyhozena.');
        } catch (DuplicatedUnifiedKeyException $duplicatedUnifiedKeyException) {
            self::assertStringContainsString(
                'using unify depth ' . ImportKeyUnifier::UNIFY_UP_TO_ALNUMS,
                $duplicatedUnifiedKeyException->getMessage(),
            );
            self::assertStringContainsString("Resulted key '-'", $duplicatedUnifiedKeyException->getMessage());
        }
    }
}
