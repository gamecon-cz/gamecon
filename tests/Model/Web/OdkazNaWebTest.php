<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Web\OdkazNaWeb;
use PHPUnit\Framework\TestCase;

class OdkazNaWebTest extends TestCase
{
    /**
     * @test
     */
    public function odkazSeOtevreVNovemOkneAKdyzUzivatelMaRozepsanyText(): void
    {
        $html = OdkazNaWeb::html('https://gamecon.cz/blog/muj-prispevek', 'Příspěvek na webu');

        self::assertStringContainsString('<a href="https://gamecon.cz/blog/muj-prispevek" target="_blank" rel="noopener">', $html);
        self::assertStringContainsString('Příspěvek na webu</a>', $html);
    }

    /**
     * @test
     */
    public function adresuAPopisVypisujeJakoTextNeJakoHtml(): void
    {
        $html = OdkazNaWeb::html('https://gamecon.cz/?a=1&b="2"', '<b>Web</b>');

        self::assertStringContainsString('href="https://gamecon.cz/?a=1&amp;b=&quot;2&quot;"', $html);
        self::assertStringContainsString('&lt;b&gt;Web&lt;/b&gt;</a>', $html);
        self::assertStringNotContainsString('<b>Web</b>', $html);
    }
}
