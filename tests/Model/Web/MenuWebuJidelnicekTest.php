<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Web\MenuWebu;

/**
 * Odkaz na jídelníček je v menu jen během GameConu, hranice GC_BEZI_OD
 * a GC_BEZI_DO včetně, stejně jako u {@see SystemoveNastaveni::gcBezi()}.
 */
class MenuWebuJidelnicekTest extends AbstractTestDb
{
    private const string GC_OD = '2099-07-16 07:00:00';
    private const string GC_DO = '2099-07-19 21:00:00';

    public static function poskytniCasy(): array
    {
        return [
            'týden před GameConem'   => ['2099-07-09 12:00:00', false],
            'sekundu před začátkem'  => ['2099-07-16 06:59:59', false],
            'přesně na začátku (od)' => [self::GC_OD, true],
            'uprostřed GameConu'     => ['2099-07-18 12:00:00', true],
            'přesně na konci (do)'   => [self::GC_DO, true],
            'sekundu po konci'       => ['2099-07-19 21:00:01', false],
        ];
    }

    /**
     * @test
     *
     * @dataProvider poskytniCasy
     */
    public function jidelnicekJeVMenuJenBehemGameconu(string $ted, bool $ocekavanVMenu): void
    {
        foreach ([null, \Uzivatel::zIdUrcite(1)] as $uzivatel) {
            $menu = MenuWebu::html($uzivatel, $this->nastaveniSCasem($ted));

            self::assertSame(
                $ocekavanVMenu,
                str_contains($menu, 'href="jidelnicek"'),
                sprintf(
                    'V čase %s %s měl být jídelníček v menu %s',
                    $ted,
                    $uzivatel ? 'pro přihlášeného' : 'pro nepřihlášeného',
                    $ocekavanVMenu ? 'vidět' : 'skrytý',
                ),
            );
            self::assertStringContainsString(
                'href="prakticke-informace"',
                $menu,
                'Ostatní položky menu mají zůstat beze změny',
            );
        }
    }

    private function nastaveniSCasem(string $ted): SystemoveNastaveni
    {
        $original = SystemoveNastaveni::zGlobals();

        return new class($original, $ted, self::GC_OD, self::GC_DO) extends SystemoveNastaveni {
            public function __construct(
                SystemoveNastaveni $original,
                private readonly string $tedStr,
                private readonly string $odStr,
                private readonly string $doStr,
            ) {
                parent::__construct(
                    rocnik: $original->rocnik(),
                    ted: $original->ted(),
                    prostredi: $original->prostredi(),
                    databazoveNastaveni: $original->databazoveNastaveni(),
                    rootAdresarProjektu: $original->rootAdresarProjektu(),
                    privateCacheDir: $original->privateCacheDir(),
                    kernel: $original->kernel(),
                    publicCacheDir: $original->publicCacheDir(),
                );
            }

            public function ted(): DateTimeImmutableStrict
            {
                return DateTimeImmutableStrict::createFromMysql($this->tedStr);
            }

            public function gcBeziOd(): DateTimeGamecon
            {
                return DateTimeGamecon::createFromMysql($this->odStr);
            }

            public function gcBeziDo(): DateTimeGamecon
            {
                return DateTimeGamecon::createFromMysql($this->doStr);
            }
        };
    }
}
