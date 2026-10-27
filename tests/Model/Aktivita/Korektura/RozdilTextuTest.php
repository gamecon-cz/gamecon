<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Aktivita\Korektura;

use Gamecon\Aktivita\Korektura\RozdilTextu;
use PHPUnit\Framework\TestCase;

class RozdilTextuTest extends TestCase
{
    /**
     * @test
     */
    public function stejneTextyNemajiZmenu(): void
    {
        self::assertSame(
            [[RozdilTextu::STEJNE, 'Hra pro dva hráče.']],
            RozdilTextu::porovnej('Hra pro dva hráče.', 'Hra pro dva hráče.'),
        );
    }

    /**
     * @test
     */
    public function pridanaCarkaJeJedinaZmena(): void
    {
        self::assertSame(
            [
                [RozdilTextu::STEJNE, 'Hráč'],
                [RozdilTextu::PRIDANO, ','],
                [RozdilTextu::STEJNE, ' který vyhraje'],
            ],
            RozdilTextu::porovnej('Hráč který vyhraje', 'Hráč, který vyhraje'),
        );
    }

    /**
     * @test
     */
    public function pridanaSlovaJsouJednoPridani(): void
    {
        self::assertSame(
            [
                [RozdilTextu::STEJNE, 'Přijďte si zahrát '],
                [RozdilTextu::PRIDANO, 's námi '],
                [RozdilTextu::STEJNE, 'dnes'],
            ],
            RozdilTextu::porovnej('Přijďte si zahrát dnes', 'Přijďte si zahrát s námi dnes'),
        );
    }

    /**
     * @test
     */
    public function zmenyUprostredTextuNajde(): void
    {
        $kusy = RozdilTextu::porovnej('Byli jsme tam, kde bily zvony.', 'Byli jsme tam, kde bili zvony.');

        self::assertContains([RozdilTextu::SMAZANO, 'bily'], $kusy);
        self::assertContains([RozdilTextu::PRIDANO, 'bili'], $kusy);
        self::assertSame(
            'Byli jsme tam, kde bili zvony.',
            implode('', array_map(
                static fn (array $kus) => $kus[0] === RozdilTextu::SMAZANO
                    ? ''
                    : $kus[1],
                $kusy,
            )),
        );
    }

    /**
     * @test
     */
    public function htmlOznaciZmenyAEscapuje(): void
    {
        self::assertSame(
            'a &lt;b&gt; <del>c</del><ins>d</ins>',
            RozdilTextu::html('a <b> c', 'a <b> d'),
        );
    }

    /**
     * @test
     */
    public function htmlZviditelniZmenuMezery(): void
    {
        self::assertSame(
            "a<del>·</del><ins>↵\n</ins>b",
            RozdilTextu::html('a b', "a\nb"),
        );
    }

    /**
     * @test
     */
    public function podilZmenPocitaZmenenaPismena(): void
    {
        self::assertSame(0.0, RozdilTextu::podilZmen('abc', 'abc'));
        self::assertEqualsWithDelta(2 / 3, RozdilTextu::podilZmen('a b', 'a c'), 0.001);
    }
}
