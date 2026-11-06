<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

class EditaceNovinkyVAdminuTest extends AbstractTestWeb
{
    private const int ID_BLOGU = 9851;
    private const int ID_NOVINKY = 9852;
    private const string URL_BLOGU = 'ukazkovy-prispevek';
    private const string URL_NOVINKY = 'ukazkova-novinka';
    private const string STRANKA_ADMINU = 'web';

    /**
     * @return array<int, string|array{0: string, 1: array<int, mixed>}>
     */
    protected static function getSetUpBeforeClassInitQueries(): array
    {
        return [
            ...parent::getSetUpBeforeClassInitQueries(),
            [
                <<<'SQL'
INSERT INTO novinky(id, typ, vydat, url, nazev, autor, text)
VALUES ($0, 2, NOW(), $1, 'Ukázkový příspěvek', 'Autor', 'Vymyšlený text'),
       ($2, 1, NOW(), $3, 'Ukázková novinka', 'Autor', 'Vymyšlený text')
SQL,
                [self::ID_BLOGU, self::URL_BLOGU, self::ID_NOVINKY, self::URL_NOVINKY],
            ],
        ];
    }

    /**
     * Editor je rozepsaný text, takže se odkaz na veřejnou stránku musí otevřít v novém okně.
     *
     * @test
     */
    public function editorPrispevkuMaOdkazNaPrispevekNaWebuVNovemOkne(): void
    {
        $telo = $this->fetchAdminPage('admin/' . self::STRANKA_ADMINU . '/novinka?id=' . self::ID_BLOGU);

        self::assertMatchesRegularExpression(
            '~<a href="[^"]*/blog/' . self::URL_BLOGU . '" target="_blank" rel="noopener"><i class="fa fa-external-link" aria-hidden="true"></i> Příspěvek na webu</a>~',
            $telo,
        );
    }

    /**
     * Novinky nemají vlastní stránku, odkaz vede na jejich výpis.
     *
     * @test
     */
    public function editorNovinkyMaOdkazNaVypisNovinekNaWebuVNovemOkne(): void
    {
        $telo = $this->fetchAdminPage('admin/' . self::STRANKA_ADMINU . '/novinka?id=' . self::ID_NOVINKY);

        self::assertMatchesRegularExpression(
            '~<a href="[^"]*/novinky" target="_blank" rel="noopener"><i class="fa fa-external-link" aria-hidden="true"></i> Výpis novinek na webu</a>~',
            $telo,
        );
    }

    /**
     * @test
     */
    public function novaNovinkaJesteNemaNaCoOdkazovat(): void
    {
        $telo = $this->fetchAdminPage('admin/' . self::STRANKA_ADMINU . '/novinka');

        self::assertStringNotContainsString('odkaz-na-web', $telo);
    }
}
