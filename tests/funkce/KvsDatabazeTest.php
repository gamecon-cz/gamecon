<?php

declare(strict_types=1);

namespace Gamecon\Tests\funkce;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class KvsDatabazeTest extends TestCase
{
    private const int POCET_PROCESU = 12;
    private const int POCET_DATABAZI = 30;

    private string $adresar;

    protected function setUp(): void
    {
        $this->adresar = SPEC . '/testy-kvs-' . getmypid();
        (new Filesystem())->mkdir($this->adresar);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->adresar);
    }

    /**
     * @test
     */
    public function soubehProcesuNeskonciChybouZamceneDatabaze(): void
    {
        $databaze = [];
        for ($poradi = 0; $poradi < self::POCET_DATABAZI; ++$poradi) {
            $soubor = "{$this->adresar}/kvs-{$poradi}.sqlite";
            // jako na ostré: soubor už po prvním použití drží režim WAL a procesy ho jen otevírají
            kvsOtevriDatabazi($soubor)->close();
            $databaze[] = $soubor;
        }

        $zacatek = microtime(true) + 2; // aby všechny procesy stihly nastartovat
        $procesy = [];
        for ($cislo = 0; $cislo < self::POCET_PROCESU; ++$cislo) {
            $pipes = [];
            $proces = proc_open(
                [PHP_BINARY, __DIR__ . '/kvs-otevri-databazi-proces.php', (string) $zacatek, ...$databaze],
                [
                    1 => ['pipe', 'w'],
                    2 => ['redirect', 1],
                ],
                $pipes,
            );
            self::assertIsResource($proces);
            $procesy[] = [$proces, $pipes];
        }

        $chyby = [];
        foreach ($procesy as [$proces, $pipes]) {
            $vystup = stream_get_contents($pipes[1]);
            proc_close($proces);
            $chyby = [...$chyby, ...array_filter(explode("\n", trim($vystup)), static fn (string $radek) => $radek !== 'OK')];
        }

        self::assertSame([], array_unique($chyby));
    }
}
