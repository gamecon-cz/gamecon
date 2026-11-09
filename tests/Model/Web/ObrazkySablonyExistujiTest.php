<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Odkazy na obrázky šablony a administrace se píšou ručně v LESS, šablonách i PHP. Přejmenovaný nebo smazaný obrázek
 * se jinak pozná až na stránce, kde chybí, a větev, která na něj odkazuje, ho po sloučení s hlavní větví nepozná vůbec.
 */
class ObrazkySablonyExistujiTest extends TestCase
{
    private const string PRIPONY = 'png|jpe?g|gif|avif|webp';

    /**
     * @test
     */
    public function kazdyObrazekSablonyAAdministraceNaKterySeOdkazujeExistuje(): void
    {
        $koren = $this->getKoren();
        $chybejici = [];
        $prohledano = 0;
        $odkazu = 0;
        foreach ($this->getZdrojoveSoubory() as $soubor) {
            ++$prohledano;
            $text = (string) file_get_contents($soubor->getPathname());
            foreach ($this->najdiOdkazy($text, $soubor->getPath()) as $odkaz => $cesta) {
                ++$odkazu;
                if (! is_file($cesta)) {
                    $chybejici[] = sprintf('%s: %s', substr($soubor->getPathname(), strlen($koren) + 1), $odkaz);
                }
            }
        }

        self::assertGreaterThan(300, $prohledano, 'Test prohledal málo souborů, nejspíš se změnily adresáře nebo jejich výjimky');
        self::assertGreaterThan(40, $odkazu, 'Test našel málo odkazů, nejspíš se změnily vzory');
        self::assertSame([], $chybejici, 'Odkaz míří na obrázek, který v repozitáři není');
    }

    /**
     * @test
     */
    public function odkazyNaObrazkySeRozpoznajiVeVsechZapisech(): void
    {
        $koren = $this->getKoren();
        $text = <<<'TEXT'
            <img src="soubory/blackarrow/prihlaska/hra.avif">
            background: url('../blackarrow/karty/smouha.png') no-repeat;
            <img src='files/design/ok-s.png' alt="x">
            url("nahodny-obrazek.png")
            https://example.cz/soubory/styl/logo.png?v=2
            TEXT;

        self::assertSame(
            [
                'soubory/blackarrow/prihlaska/hra.avif' => $koren . '/web/soubory/blackarrow/prihlaska/hra.avif',
                'soubory/styl/logo.png'                 => $koren . '/web/soubory/styl/logo.png',
                '../blackarrow/karty/smouha.png'        => $koren . '/web/soubory/blackarrow/karty/smouha.png',
                'files/design/ok-s.png'                 => $koren . '/admin/files/design/ok-s.png',
                'nahodny-obrazek.png'                   => '/adresar-souboru/nahodny-obrazek.png',
            ],
            $this->najdiOdkazy($text, '/adresar-souboru'),
        );
    }

    private function getKoren(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @return iterable<\SplFileInfo>
     */
    private function getZdrojoveSoubory(): iterable
    {
        $koren = $this->getKoren();

        return (new Finder())
            ->files()
            ->in([$koren . '/web', $koren . '/admin', $koren . '/model'])
            ->name(['*.php', '*.xtpl', '*.html', '*.less', '*.css', '*.js'])
            ->notName(['*.min.css', '*.min.js', 'highcharts-*.js'])
            // sestavené balíčky a knihovny, nahrané soubory a cache nejsou zdroj, na který by se odkazovalo ručně
            ->exclude(['ui', 'font-awesome-6.1.1', 'obsah', 'systemove']);
    }

    /**
     * @param string $adresarSouboru adresář souboru, ve kterém je $text, vůči němu se čte `url('obrazek.png')` bez složky
     *
     * @return array<string, string> odkaz tak, jak je napsaný => cesta k souboru
     */
    private function najdiOdkazy(string $text, string $adresarSouboru): array
    {
        $koren = $this->getKoren();
        $pripony = self::PRIPONY;
        $odkazy = [];
        $pravidla = [
            "~soubory/((?:blackarrow|styl)/[^'\"\\s)?#<>{}]+\\.(?:{$pripony}))~i"   => $koren . '/web/soubory/',
            "~\\.\\./(?:\\.\\./)?(blackarrow/[^'\"\\s)?#<>{}]+\\.(?:{$pripony}))~i" => $koren . '/web/soubory/',
            "~files/design/([^'\"\\s)?#<>{}]+\\.(?:{$pripony}))~i"                  => $koren . '/admin/files/design/',
        ];
        foreach ($pravidla as $vzor => $predpona) {
            preg_match_all($vzor, $text, $shody, PREG_SET_ORDER);
            foreach ($shody as $shoda) {
                $odkazy[$shoda[0]] = $predpona . $shoda[1];
            }
        }
        // `url('obrazek.png')` bez složky vede vedle stylu, který na něj odkazuje
        preg_match_all("~(?:url|data-uri)\\(\\s*['\"]?([\\w.-]+\\.(?:{$pripony}))['\"]?\\s*\\)~i", $text, $shody, PREG_SET_ORDER);
        foreach ($shody as $shoda) {
            $odkazy[$shoda[1]] = $adresarSouboru . '/' . $shoda[1];
        }

        return $odkazy;
    }
}
