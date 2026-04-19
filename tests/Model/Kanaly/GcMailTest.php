<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Kanaly;

use Gamecon\Kanaly\GcMail;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use PHPUnit\Framework\TestCase;

class GcMailTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/gc-mail-test-' . getmypid() . '-' . mt_rand();
        mkdir($this->tempDir, 0770, true);
    }

    protected function tearDown(): void
    {
        $this->smazRekurzivne($this->tempDir);
    }

    private function smazRekurzivne(string $cesta): void
    {
        if (! file_exists($cesta)) {
            return;
        }
        if (is_dir($cesta)) {
            foreach (scandir($cesta) as $polozka) {
                if ($polozka === '.' || $polozka === '..') {
                    continue;
                }
                $this->smazRekurzivne($cesta . '/' . $polozka);
            }
            rmdir($cesta);
        } else {
            unlink($cesta);
        }
    }

    private function vytvorGcMail(): GcMail
    {
        $systemoveNastaveni = $this->createMock(SystemoveNastaveni::class);
        $systemoveNastaveni->method('kontaktniEmailGc')->willReturn('test@example.com');
        $systemoveNastaveni->method('prefixPodleProstredi')->willReturn('test');

        return new GcMail($systemoveNastaveni);
    }

    /**
     * @test
     */
    public function zalogovatDoZapiseObsahDoSouboru(): void
    {
        $soubor = $this->tempDir . '/test.log';
        $obsah = "testovací obsah\n";

        $gcMail = $this->vytvorGcMail();
        $reflection = new \ReflectionMethod($gcMail, 'zalogovatDo');
        $vysledek = $reflection->invoke($gcMail, $soubor, $obsah);

        self::assertTrue($vysledek);
        self::assertStringEqualsFile($soubor, $obsah);
    }

    /**
     * @test
     */
    public function zalogovatDoVratiFalseProNeplatnouCestu(): void
    {
        // Aby selhalo vytvoření i pod uživatelem root v Dockeru, vytvoříme soubor
        // a pokusíme se do něj zapisovat jako do složky.
        $blokujiciSoubor = $this->tempDir . '/soubor_misto_adresare.txt';
        touch($blokujiciSoubor);
        chmod($blokujiciSoubor, 0000); // pro jistotu

        $soubor = $blokujiciSoubor . '/adresar/test.log';

        $gcMail = $this->vytvorGcMail();
        $reflection = new \ReflectionMethod($gcMail, 'zalogovatDo');
        $vysledek = $reflection->invoke($gcMail, $soubor, 'obsah');

        self::assertFalse($vysledek);
    }

    /**
     * @test
     */
    public function rotaceProbehnePriVelkemSouboru(): void
    {
        $soubor = $this->tempDir . '/maily-odeslane.log';
        file_put_contents($soubor, str_repeat('x', 100));

        $gcMail = $this->vytvorGcMail();

        $reflection = new \ReflectionMethod($gcMail, 'rotujAuditLogPokudJeMocVelky');

        // Původní obsah má nějakou velikost. Dáme takový přírůstek v bajtech,
        // který bezpečně překročí i výchozí limit max. velikosti souboru.
        // Tím se vyhneme redefinici globální konstanty.
        $prirustekKteryZpusobiRotaci = 100 * 1024 * 1024; // 100 MB
        $vysledek = $reflection->invoke($gcMail, $soubor, $prirustekKteryZpusobiRotaci);

        // Soubor měl být přejmenován (rotován)
        self::assertTrue($vysledek);
        self::assertFileDoesNotExist($soubor);

        // Rotovaný soubor by měl existovat
        $rotovane = glob($this->tempDir . '/maily-odeslane-*.log');
        self::assertCount(1, $rotovane);
    }

    /**
     * @test
     */
    public function smazStareRotovaneLogyOdstraniSouboryStarsiNezDvaRoky(): void
    {
        $gcMail = $this->vytvorGcMail();

        // Vytvoříme "starý" rotovaný soubor (3 roky starý)
        $starySoubor = $this->tempDir . '/maily-odeslane-20230101-120000.log';
        file_put_contents($starySoubor, 'stary obsah');
        touch($starySoubor, time() - 3 * 365 * 24 * 3600);

        // Vytvoříme "nový" rotovaný soubor (1 rok starý)
        $novySoubor = $this->tempDir . '/maily-odeslane-20250401-120000.log';
        file_put_contents($novySoubor, 'novy obsah');
        touch($novySoubor, time() - 365 * 24 * 3600);

        $hlavniSoubor = $this->tempDir . '/maily-odeslane.log';

        $reflection = new \ReflectionMethod($gcMail, 'smazStareRotovaneLogy');
        $reflection->invoke($gcMail, $hlavniSoubor);

        self::assertFileDoesNotExist($starySoubor, 'Starý rotovaný log (3 roky) měl být smazán');
        self::assertFileExists($novySoubor, 'Nový rotovaný log (1 rok) měl být zachován');
    }

    /**
     * @test
     */
    public function auditLogujeChybuKdyzZapisDoSouboruSelze(): void
    {
        $auditLog = LOGY . '/maily-odeslane.log';
        if (file_exists($auditLog)) {
            unlink($auditLog);
        }

        $systemoveNastaveni = $this->createMock(SystemoveNastaveni::class);
        $systemoveNastaveni->method('kontaktniEmailGc')->willReturn('test@example.com');
        $systemoveNastaveni->method('prefixPodleProstredi')->willReturn('test');

        // Anonymní podtřída přepisuje jedinou protected metodu (I/O seam),
        // aby uložení mailu selhalo, ale zápis auditu prošel.
        $gcMail = new class($systemoveNastaveni) extends GcMail {
            protected function zalogovatDo(string $soubor, string $obsah): bool
            {
                // Audit log (maily-odeslane.log) necháme zapsat normálně
                if (str_ends_with($soubor, 'maily-odeslane.log')) {
                    return parent::zalogovatDo($soubor, $obsah);
                }

                // Vše ostatní (uložení mailu do souboru) selže
                return false;
            }
        };

        $gcMail->adresati(['test@example.com']);
        $gcMail->odeslat();

        self::assertFileExists($auditLog);
        $obsah = file_get_contents($auditLog);
        self::assertStringContainsString('"stav":"chyba_ulozeni_do_souboru"', $obsah);
    }
}
