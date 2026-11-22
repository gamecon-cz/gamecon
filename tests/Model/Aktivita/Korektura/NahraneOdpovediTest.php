<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Aktivita\Korektura;

use Gamecon\Aktivita\Korektura\NahraneOdpovedi;
use PHPUnit\Framework\TestCase;

class NahraneOdpovediTest extends TestCase
{
    /**
     * @var array<string>
     */
    private array $docasneSoubory = [];

    protected function tearDown(): void
    {
        foreach ($this->docasneSoubory as $soubor) {
            if (is_file($soubor)) {
                unlink($soubor);
            }
        }
        parent::tearDown();
    }

    /**
     * @test
     */
    public function kazdyNenahranySouborSeOhlasiJmenem(): void
    {
        $nahrane = NahraneOdpovedi::zFormulare([
            'name'     => ['a.txt', 'velky.txt', 'cast.txt', 'podvrh.txt', ''],
            'tmp_name' => [$this->soubor('odpověď A'), '', '', '/etc/passwd', ''],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_PARTIAL, UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE],
        ], 20, fn (string $cesta) => in_array($cesta, $this->docasneSoubory, true));

        self::assertSame([
            'a.txt' => 'odpověď A',
        ], $nahrane->odpovediPodleSouboru);
        self::assertSame([
            'velky.txt: nenahrál se (je moc velký), nahraj ho znovu.',
            'cast.txt: nenahrál se (přenesl se jen zčásti), nahraj ho znovu.',
            'podvrh.txt: nenahrál se, nahraj ho znovu.',
        ], $nahrane->varovani, 'Pole bez souboru není chyba, soubor, který nepřišel nahráním, se nečte');
    }

    /**
     * @test
     */
    public function stejnojmenneSouboryZustanouOba(): void
    {
        $nahrane = NahraneOdpovedi::zFormulare([
            'name'     => ['odpoved.txt', 'odpoved.txt'],
            'tmp_name' => [$this->soubor('první'), $this->soubor('druhá')],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
        ], 20, static fn () => true);

        self::assertSame([
            'odpoved.txt'     => 'první',
            'odpoved.txt (2)' => 'druhá',
        ], $nahrane->odpovediPodleSouboru);
    }

    /**
     * PHP leaves the files over max_file_uploads out without an error, so as many as the limit means some may be missing.
     *
     * @test
     */
    public function naLimituPoctuSouboruVaruje(): void
    {
        $soubory = [
            'name'     => ['1.txt', '2.txt'],
            'tmp_name' => [$this->soubor('1'), $this->soubor('2')],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
        ];

        self::assertSame([], NahraneOdpovedi::zFormulare($soubory, 3, static fn () => true)->varovani);
        self::assertStringContainsString(
            'nejvýš 2 souborů',
            implode(' ', NahraneOdpovedi::zFormulare($soubory, 2, static fn () => true)->varovani),
        );
        self::assertSame([], NahraneOdpovedi::zFormulare(null, 2)->odpovediPodleSouboru, 'Bez pole souborů nic');
    }

    private function soubor(string $obsah): string
    {
        $cesta = (string) tempnam(sys_get_temp_dir(), 'nahrane-odpovedi-test-');
        file_put_contents($cesta, $obsah);
        $this->docasneSoubory[] = $cesta;

        return $cesta;
    }
}
