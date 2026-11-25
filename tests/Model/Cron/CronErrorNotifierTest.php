<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Cron;

use Gamecon\Cron\CronErrorNotifier;
use PHPUnit\Framework\TestCase;

class CronErrorNotifierTest extends TestCase
{
    public function testMailRikaCoSelhaloIProc(): void
    {
        $pricina = new \RuntimeException('Lock wait timeout exceeded');
        $chyba = new \RuntimeException('Archiv přednášek 2026: „Přednáška“ (aktivita 1) selhala', 0, $pricina);

        $text = CronErrorNotifier::textChyby($chyba);

        self::assertStringStartsWith('RuntimeException: Archiv přednášek 2026: „Přednáška“ (aktivita 1) selhala (', $text);
        self::assertStringContainsString("\nPříčina: RuntimeException: Lock wait timeout exceeded (", $text);
        self::assertStringContainsString($chyba->getTraceAsString(), $text);
    }
}
