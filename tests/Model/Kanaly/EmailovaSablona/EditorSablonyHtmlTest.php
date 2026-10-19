<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Kanaly\EmailovaSablona;

use Gamecon\Kanaly\EmailovaSablona\EditorSablonyHtml;
use Gamecon\Kanaly\EmailovaSablona\EmailoveSablonyKatalog;
use Gamecon\Kanaly\EmailovaSablona\SystemoveHodnotyProSablony;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\XTemplate\XTemplate;

class EditorSablonyHtmlTest extends AbstractTestDb
{
    protected function setUp(): void
    {
        parent::setUp();

        // Without it XTemplate compiles the template next to its source, into the tracked tree
        $cacheDir = XTemplate::cache() ?: XTPL_CACHE_DIR;
        pripravCache($cacheDir);
        XTemplate::cache($cacheDir);
    }

    /**
     * @test
     */
    public function tlacitkoProOtevreniSchrankyVedeNaZadanouAdresuDoNovehoOkna(): void
    {
        $html = $this->vykresli(schrankaUrl: 'http://localhost:11112');

        self::assertMatchesRegularExpression(
            '~<button type="button" class="editorSablony_schranka" data-url="http://localhost:11112"[^>]*>(?:<i [^>]*></i> )?Otevřít schránku</button>~u',
            $html,
        );
    }

    /**
     * @test
     */
    public function adresaSchrankySeEscapujeDoAtributu(): void
    {
        $html = $this->vykresli(schrankaUrl: 'https://webmail.test/?gate=a&b="c"');

        self::assertStringContainsString('data-url="https://webmail.test/?gate=a&amp;b=&quot;c&quot;"', $html);
    }

    /**
     * @test
     */
    public function bezAdresySchrankyTlacitkoNeniVubec(): void
    {
        $html = $this->vykresli(schrankaUrl: null);

        self::assertStringNotContainsString('editorSablony_schranka', $html);
        self::assertStringNotContainsString('Otevřít schránku', $html);
    }

    /**
     * @test
     */
    public function tlacitkoSchrankyJeHnedZaTlacitkemOdeslatTest(): void
    {
        $html = $this->vykresli(schrankaUrl: 'http://localhost:11112');

        self::assertMatchesRegularExpression(
            '~editorSablony_test">.*?Odeslat test</button>\s*<button[^>]*editorSablony_schranka.*?Otevřít schránku</button>\s*<button[^>]*editorSablony_vlozit~su',
            $html,
        );
    }

    private function vykresli(?string $schrankaUrl): string
    {
        return (new EditorSablonyHtml(new SystemoveHodnotyProSablony(SystemoveNastaveni::zGlobals())))->vykresli(
            EmailoveSablonyKatalog::dej('uvolnene_misto'),
            'Předmět',
            'Text',
            [],
            'nahled',
            'seznam',
            'Zpět',
            'test',
            'admin@example.test',
            $schrankaUrl,
        );
    }
}
