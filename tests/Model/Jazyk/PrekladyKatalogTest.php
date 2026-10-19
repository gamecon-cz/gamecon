<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Jazyk;

use Gamecon\Jazyk\Jazyk;
use Gamecon\Jazyk\Preklad;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Chybějící překlad nikde nespadne: web prostě ukáže češtinu nebo samotný klíč. Hlídáme to tady.
 */
class PrekladyKatalogTest extends TestCase
{
    /**
     * Kde se volá t('klic') nebo píše {t:klic}
     */
    private const array ADRESARE_S_KODEM = ['model', 'web', 'admin', 'nastaveni', 'symfony/src'];

    public function testVsechnyJazykyMajiStejneKlice(): void
    {
        $cesky = array_keys($this->katalog(Jazyk::CESTINA));
        foreach (Jazyk::cases() as $jazyk) {
            $klice = array_keys($this->katalog($jazyk));
            self::assertSame([], array_values(array_diff($cesky, $klice)), "V katalogu '{$jazyk->value}' chybí klíče.");
            self::assertSame([], array_values(array_diff($klice, $cesky)), "Katalog '{$jazyk->value}' má klíče navíc.");
        }
    }

    public function testKazdyPouzityKlicJeVKatalogu(): void
    {
        $chybejici = array_diff(array_keys($this->pouziteKlice()), array_keys($this->katalog(Jazyk::CESTINA)));

        self::assertSame([], array_values($chybejici));
    }

    public function testVKataloguNeniNicNepouziteho(): void
    {
        $nepouzite = array_diff(array_keys($this->katalog(Jazyk::CESTINA)), array_keys($this->pouziteKlice()));

        self::assertSame([], array_values($nepouzite));
    }

    public function testPrekladyMajiStejneParametryAJdouZformatovat(): void
    {
        $cesky = $this->katalog(Jazyk::CESTINA);
        foreach (Jazyk::cases() as $jazyk) {
            foreach ($this->katalog($jazyk) as $klic => $text) {
                self::assertNotFalse(
                    \MessageFormatter::create($jazyk->value, $text),
                    "Text '{$klic}' v '{$jazyk->value}' není platná zpráva ICU.",
                );
                self::assertSame(
                    $this->parametry($cesky[$klic] ?? ''),
                    $this->parametry($text),
                    "Text '{$klic}' v '{$jazyk->value}' má jiné parametry než česky.",
                );
            }
        }
    }

    public function testChybejiciAnglickyTextSpadneNaCestinu(): void
    {
        self::assertSame('Můj program', Preklad::t('program.nadpis.muj', [], Jazyk::CESTINA));
        self::assertSame('My programme', Preklad::t('program.nadpis.muj', [], Jazyk::ANGLICTINA));
        self::assertSame('Programme – pátek', Preklad::t('program.nadpis.den', [
            'den' => 'pátek',
        ], Jazyk::ANGLICTINA));
    }

    /**
     * @return array<string, string> plochý klíč => text
     */
    private function katalog(Jazyk $jazyk): array
    {
        $ploche = [];
        $zplosti = static function (array $uzel, string $prefix) use (&$zplosti, &$ploche): void {
            foreach ($uzel as $klic => $hodnota) {
                if (is_array($hodnota)) {
                    $zplosti($hodnota, $prefix . $klic . '.');
                } else {
                    $ploche[$prefix . $klic] = (string) $hodnota;
                }
            }
        };
        $zplosti(Yaml::parseFile(Preklad::souborKatalogu($jazyk)) ?? [], '');

        return $ploche;
    }

    /**
     * @return array<string, true>
     */
    private function pouziteKlice(): array
    {
        $koren = __DIR__ . '/../../..';
        $klice = [];
        $soubory = (new Finder())->files()
            ->in(array_map(static fn (string $adresar) => $koren . '/' . $adresar, self::ADRESARE_S_KODEM))
            ->name(['*.php', '*.xtpl'])
            ->exclude(['files', 'soubory']);
        foreach ($soubory as $soubor) {
            $kod = $soubor->getContents();
            preg_match_all("~(?<![\\w>:$])t\\(\\s*'([a-z][a-z0-9_]*(?:\\.[a-z][a-z0-9_]*)+)'~", $kod, $volani);
            preg_match_all('~\{t:([a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+)\}~', $kod, $sablony);
            foreach ([...$volani[1], ...$sablony[1]] as $klic) {
                $klice[$klic] = true;
            }
        }
        self::assertNotSame([], $klice, 'Hledání klíčů v kódu nic nenašlo, test by nic nehlídal.');

        return $klice;
    }

    /**
     * @return list<string>
     */
    private function parametry(string $text): array
    {
        preg_match_all('~\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*[,}]~', $text, $nalezy);
        $parametry = array_values(array_unique($nalezy[1]));
        sort($parametry);

        return $parametry;
    }
}
