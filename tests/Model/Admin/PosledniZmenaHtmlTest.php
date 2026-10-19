<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Admin;

use Gamecon\Admin\PosledniZmenaHtml;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Tests\Db\AbstractTestDb;

class PosledniZmenaHtmlTest extends AbstractTestDb
{
    /**
     * @test
     */
    public function relativniCasAJmenoSeVraciZvlast(): void
    {
        $kdy = new \DateTimeImmutable('2026-09-23 14:01:15');

        $casti = PosledniZmenaHtml::dej($kdy, $this->uzivatelSJmenem('Jaroslav „Kostřivec“ Týc'));

        self::assertSame(DateTimeCz::createFromInterface($kdy)->relativni(), $casti['relativne']);
        self::assertSame('<strong>Jaroslav „Kostřivec“ Týc</strong><br>23. 9. 2026 14:01:15', $casti['napoveda']);
    }

    /**
     * @test
     */
    public function jmenoSeEscapuje(): void
    {
        $casti = PosledniZmenaHtml::dej(new \DateTimeImmutable('2026-09-23 14:01:15'), $this->uzivatelSJmenem('A&B <i>x</i>'));

        self::assertStringContainsString('<strong>A&amp;B &lt;i&gt;x&lt;/i&gt;</strong>', $casti['napoveda']);
    }

    /**
     * @test
     */
    public function neznamyAutorSeBereJakoUzivatelSystem(): void
    {
        $systemovyUzivatel = \Uzivatel::zId(\Uzivatel::SYSTEM);
        self::assertNotNull($systemovyUzivatel);

        $casti = PosledniZmenaHtml::dej(new \DateTimeImmutable('2026-09-23 14:01:15'), null);

        self::assertStringStartsWith('<strong>' . htmlspecialchars($systemovyUzivatel->jmenoNick(), ENT_QUOTES) . '</strong>', $casti['napoveda']);
    }

    /**
     * @test
     */
    public function bunkaSkladaRelativniCasANapovedu(): void
    {
        $kdy = new \DateTimeImmutable('2026-09-23 14:01:15');
        $uzivatel = $this->uzivatelSJmenem('Jan Novák');
        $casti = PosledniZmenaHtml::dej($kdy, $uzivatel);

        self::assertSame(
            $casti['relativne'] . '<span class="hint">' . $casti['napoveda'] . '</span>',
            PosledniZmenaHtml::bunka($kdy, $uzivatel),
        );
    }

    private function uzivatelSJmenem(string $jmeno): \Uzivatel
    {
        return new class($jmeno) extends \Uzivatel {
            public function __construct(
                private readonly string $jmeno,
            ) {
            }

            public function jmenoNick(): ?string
            {
                return $this->jmeno;
            }
        };
    }
}
