<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Web\CestyKSitemape;
use PHPUnit\Framework\TestCase;

/**
 * Whether a module is a public page is not written anywhere in the code, so the sitemap keeps a list.
 * This test compares the list with the real directory web/moduly: a new, renamed or removed module has to be decided.
 */
class ModulyVSitemapeTest extends TestCase
{
    private const array MODULY_MIMO_SITEMAPU = [
        'ajax-vyjimkovac'    => 'zápis chyb z prohlížeče, ne stránka',
        'aktivity'           => 'vstup pro linie a profily vypravěčů, které mají vlastní adresy; samotné /aktivity v menu není',
        'api'                => 'JSON',
        'discord'            => 'přesměrování na externí web',
        'facebook'           => 'přesměrování na externí web',
        'finance'            => 'jen pro přihlášené',
        'info-po-gc'         => 'přesměrování',
        'instagram'          => 'přesměrování na externí web',
        'mail'               => 'přesměrování na externí nástroj',
        'nenalezeno'         => 'chybová stránka',
        'neprihlasen'        => 'chybová stránka',
        'newslettery'        => 'formulář k odběru newsletteru',
        'odhlas-z-mailu'     => 'akce z odkazu v e-mailu',
        'poukaz'             => 'formulář',
        'prihlaseni'         => 'přihlášení',
        'prihlaska'          => 'formulář přihlášky',
        'program-nahled-api' => 'JSON',
        'qrka'               => 'jen pro přihlášené',
        'registrace'         => 'formulář',
        'rss'                => 'kanál, ne stránka',
        'stranka'            => 'obsluha stránek ze správy, ty jsou v mapě zvlášť',
        'titulka'            => 'úvodní stránka, v mapě jako prázdná cesta',
        'wordpress'          => 'past na skenery',
        'wp'                 => 'past na skenery',
        'wp-json'            => 'past na skenery',
        'youtube'            => 'přesměrování na externí web',
    ];

    /**
     * @test
     */
    public function kazdyModulWebuJeVSitemapeNeboVyslovneVynechany(): void
    {
        $skutecneModuly = [];
        foreach (scandir(WWW . '/moduly') as $polozka) {
            if ($polozka !== '.' && $polozka !== '..') {
                $skutecneModuly[] = basename($polozka, '.php');
            }
        }
        $rozhodnuteModuly = [...CestyKSitemape::VEREJNE_MODULY, ...array_keys(self::MODULY_MIMO_SITEMAPU)];

        self::assertSame(
            [],
            array_values(array_diff($skutecneModuly, $rozhodnuteModuly)),
            'Nový modul ve web/moduly: je to veřejná stránka (CestyKSitemape::VEREJNE_MODULY), nebo patří do MODULY_MIMO_SITEMAPU?',
        );
        self::assertSame(
            [],
            array_values(array_diff($rozhodnuteModuly, $skutecneModuly)),
            'Modul, který už ve web/moduly není, zůstal v CestyKSitemape::VEREJNE_MODULY nebo v MODULY_MIMO_SITEMAPU',
        );
    }

    /**
     * @test
     */
    public function modulNeniVSitemapeAMimoSitemapuZaroven(): void
    {
        self::assertSame(
            [],
            array_values(array_intersect(CestyKSitemape::VEREJNE_MODULY, array_keys(self::MODULY_MIMO_SITEMAPU))),
        );
    }
}
