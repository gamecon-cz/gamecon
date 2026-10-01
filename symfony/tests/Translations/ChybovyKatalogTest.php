<?php

declare(strict_types=1);

namespace App\Tests\Translations;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * A key missing from the catalogue, or a placeholder the caller does not pass, fails nowhere:
 * the user just gets the key, or a literal `%product%`, as the message.
 */
class ChybovyKatalogTest extends TestCase
{
    private const KATALOG = __DIR__ . '/../../translations/errors.cs.yaml';

    public function testKazdyPouzityKlicJeVKatalogu(): void
    {
        self::assertSame([], array_values(array_diff(array_keys($this->pouziteKlice()), array_keys($this->katalog()))));
    }

    public function testVKataloguNeniNicNepouziteho(): void
    {
        self::assertSame([], array_values(array_diff(array_keys($this->katalog()), array_keys($this->pouziteKlice()))));
    }

    /**
     * Per file, not per call: a call that omits a placeholder another call in the same file passes goes unnoticed.
     */
    public function testKazdyZastupnyZnakDodavaKodKteryKlicPouziva(): void
    {
        $katalog = $this->katalog();
        $chybejici = [];
        foreach ($this->pouziteKlice() as $klic => $kody) {
            preg_match_all('~%[a-z_]+%~', $katalog[$klic] ?? '', $zastupne);
            foreach (array_unique($zastupne[0]) as $zastupny) {
                foreach ($kody as $soubor => $kod) {
                    if (! str_contains($kod, "'" . $zastupny . "'")) {
                        $chybejici[] = $klic . ' ' . $zastupny . ' v ' . $soubor;
                    }
                }
            }
        }

        self::assertSame([], $chybejici);
    }

    /**
     * @return array<string, array<string, string>> klíč => [soubor => obsah souboru]
     */
    private function pouziteKlice(): array
    {
        $klice = [];
        foreach ((new Finder())->files()->in(__DIR__ . '/../../src')->name('*.php') as $soubor) {
            $kod = $soubor->getContents();
            preg_match_all("~->trans\\(\\s*'([^']+)'~", $kod, $preklady);
            preg_match_all("~verifyOperator\\(\\s*'([^']+)'~", $kod, $akce);
            foreach ([...$preklady[1], ...$akce[1]] as $klic) {
                $klice[$klic][$soubor->getRelativePathname()] = $kod;
            }
        }
        self::assertNotSame([], $klice, 'Hledání klíčů v kódu nic nenašlo, test by nic nehlídal.');

        return $klice;
    }

    /**
     * @return array<string, string> plochý klíč => text
     */
    private function katalog(): array
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
        $zplosti(Yaml::parseFile(self::KATALOG), '');

        return $ploche;
    }
}
