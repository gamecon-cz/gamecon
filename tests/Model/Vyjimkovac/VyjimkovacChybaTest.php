<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Vyjimkovac;

use Gamecon\Vyjimkovac\VyjimkovacChyba;
use PHPUnit\Framework\TestCase;

class VyjimkovacChybaTest extends TestCase
{
    /**
     * @test
     */
    public function muzuZiskatUrlDetailuChyby()
    {
        self::assertFileExists(
            __DIR__ . '/../../../admin/scripts/modules/dev/chyby.php',
            'Cesta ke skriptu pro zpracování chyb se změnila, URL pro detail chyby už neplatí a je nutné ho změnit'
        );
        self::assertSame(
            '/dev/chyby?vyjimka=123',
            VyjimkovacChyba::urlDetailuChyby(123)
        );
        self::assertSame(
            '/dev/chyby?' . VyjimkovacChyba::VYJIMKA . '=123',
            VyjimkovacChyba::urlDetailuChyby(123)
        );
        self::assertSame(
            'http://admin.gamecon.kdesi/dev/chyby?vyjimka=123',
            VyjimkovacChyba::absolutniUrlDetailuChyby(123, 'http://admin.gamecon.kdesi')
        );
    }
}
