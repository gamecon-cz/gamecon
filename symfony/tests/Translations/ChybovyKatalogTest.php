<?php

declare(strict_types=1);

namespace App\Tests\Translations;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * A key missing from the catalogue does not fail anywhere: the user just gets the key itself as the message.
 */
class ChybovyKatalogTest extends TestCase
{
    private const KATALOG = __DIR__ . '/../../translations/errors.cs.yaml';

    public function testKazdyPouzityKlicJeVKatalogu(): void
    {
        self::assertSame([], array_values(array_diff($this->pouziteKlice(), $this->klicKatalogu())));
    }

    public function testVKataloguNeniNicNepouziteho(): void
    {
        self::assertSame([], array_values(array_diff($this->klicKatalogu(), $this->pouziteKlice())));
    }

    /**
     * @return list<string>
     */
    private function pouziteKlice(): array
    {
        $klice = [];
        foreach ((new Finder())->files()->in(__DIR__ . '/../../src')->name('*.php') as $soubor) {
            $kod = $soubor->getContents();
            preg_match_all("~->trans\\('([a-z_.]+)'~", $kod, $preklady);
            preg_match_all("~verifyOperator\\('([a-z_.]+)'\\)~", $kod, $akce);
            array_push($klice, ...$preklady[1], ...$akce[1]);
        }
        self::assertNotSame([], $klice, 'Hledání klíčů v kódu nic nenašlo, test by nic nehlídal.');

        return array_values(array_unique($klice));
    }

    /**
     * @return list<string>
     */
    private function klicKatalogu(): array
    {
        $ploche = [];
        $zplosti = static function (array $uzel, string $prefix) use (&$zplosti, &$ploche): void {
            foreach ($uzel as $klic => $hodnota) {
                is_array($hodnota)
                    ? $zplosti($hodnota, $prefix . $klic . '.')
                    : $ploche[] = $prefix . $klic;
            }
        };
        $zplosti(Yaml::parseFile(self::KATALOG), '');

        return $ploche;
    }
}
