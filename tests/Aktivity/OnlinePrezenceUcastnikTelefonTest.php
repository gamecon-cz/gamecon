<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceUcastnikHtml;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractUzivatelTestDb;

/**
 * Ověřuje, že okno „telefony viditelné" v prezenci je vůči injektovanému času
 * ({@see SystemoveNastaveni::ted()}) a že hranice GC_BEZI_OD / GC_BEZI_DO jsou
 * inkluzivní – stejně jako kanonické {@see SystemoveNastaveni::gcBezi()} přes mezi().
 */
class OnlinePrezenceUcastnikTelefonTest extends AbstractUzivatelTestDb
{
    private const GC_OD = '2099-06-18 20:00:00';
    private const GC_DO = '2099-06-22 18:00:00';

    protected static function resetDbAfterClass(): bool
    {
        return true;
    }

    public static function poskytniCasy(): array
    {
        return [
            'sekundu před začátkem'     => ['2099-06-18 19:59:59', false],
            'přesně na začátku (od)'    => [self::GC_OD, true],
            'uprostřed'                 => ['2099-06-20 12:00:00', true],
            'přesně na konci (do)'      => [self::GC_DO, true],
            'sekundu po konci'          => ['2099-06-22 18:00:01', false],
        ];
    }

    /**
     * @dataProvider poskytniCasy
     */
    public function testGcBeziPodleInjektovanehoCasuRespektujeInkluzivniHranice(
        string $ted,
        bool   $ocekavanoBezi,
    ): void {
        $ucastnikHtml = new OnlinePrezenceUcastnikHtml($this->nastaveniSCasem($ted));

        $metoda = new \ReflectionMethod($ucastnikHtml, 'gcBeziPodleInjektovanehoCasu');
        $metoda->setAccessible(true);

        self::assertSame(
            $ocekavanoBezi,
            $metoda->invoke($ucastnikHtml),
            "Pro čas $ted mělo být 'GC běží' = " . ($ocekavanoBezi ? 'true' : 'false'),
        );
    }

    public static function poskytniDivaky(): array
    {
        // [divák je organizátor, GC běží (čas uvnitř okna), očekávaná viditelnost telefonu]
        return [
            'organizátor mimo GC vidí'          => [true,  false, true],
            'organizátor během GC vidí'         => [true,  true,  true],
            'neorganizátor mimo GC nevidí'      => [false, false, false],
            'neorganizátor během GC vidí'       => [false, true,  true],
        ];
    }

    /**
     * @dataProvider poskytniDivaky
     */
    public function testViditelnostTelefonuZaviziNaRoliDivaka(
        bool $divakJeOrganizator,
        bool $gcBezi,
        bool $ocekavanaViditelnost,
    ): void {
        $ted          = $gcBezi ? '2099-06-20 12:00:00' : '2099-06-01 12:00:00';
        $ucastnikHtml = new OnlinePrezenceUcastnikHtml($this->nastaveniSCasem($ted));

        $vypravec = $this->createMock(\Uzivatel::class);
        $vypravec->method('jeOrganizator')->willReturn($divakJeOrganizator);

        $metoda = new \ReflectionMethod($ucastnikHtml, 'smiZobrazitTelefon');
        $metoda->setAccessible(true);

        self::assertSame(
            $ocekavanaViditelnost,
            $metoda->invoke($ucastnikHtml, $vypravec),
        );
    }

    private function nastaveniSCasem(string $ted): SystemoveNastaveni
    {
        $original = SystemoveNastaveni::zGlobals();

        return new class($original, $ted, self::GC_OD, self::GC_DO) extends SystemoveNastaveni {
            public function __construct(
                SystemoveNastaveni      $original,
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

            public function prihlaseniNaPosledniChviliXMinutPredZacatkemAktivity(): int
            {
                return 15;
            }
        };
    }
}
