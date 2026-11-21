<?php

declare(strict_types=1);

use Gamecon\Jazyk\AktualniJazyk;
use Gamecon\Jazyk\Jazyk;
use Gamecon\Jazyk\Preklad;
use Gamecon\XTemplate\XTemplate;
use Symfony\Component\Filesystem\Filesystem;

class XTemplateTest extends PHPUnit\Framework\TestCase
{
    private ?string $puvodniCache = null;

    public function setUp(): void
    {
        $this->puvodniCache = XTemplate::cache();
        (new Filesystem())->mkdir(__DIR__ . '/cache', 0775);
    }

    protected function tearDown(): void
    {
        // Adresář cache je statický pro celý proces. Šablona, kterou pak některý test zkompiluje do jiného
        // adresáře než předtím jiný test, by deklarovala stejnou třídu podruhé (fatal error celého běhu).
        XTemplate::cache($this->puvodniCache);
        (new Filesystem())->remove(__DIR__ . '/cache');
        foreach (glob(__DIR__ . '/resources/*.xtpc') ?: [] as $cacheSoubor) {
            unlink($cacheSoubor);
        }
    }

    public function testPrekladVJazycePozadavku()
    {
        try {
            AktualniJazyk::nastav(Jazyk::ANGLICTINA);
            $template = new XTemplate(__DIR__ . '/resources/preklad.xtpl');
            $template->parse('page');
            $anglicky = $template->text('page');

            AktualniJazyk::nastav(Jazyk::CESTINA);
            $template->parse('page');
            $cesky = $template->text('page');
        } finally {
            AktualniJazyk::reset();
        }

        self::assertStringContainsString('>programme<', $anglicky);
        self::assertStringContainsString('>program<', $cesky, 'Jedna zkompilovaná šablona slouží všem jazykům.');
        self::assertStringContainsString('{t:x}', $cesky, 'Bez tečky v klíči nejde o překlad, ale třeba o objekt v JS.');
    }

    /**
     * t() vrací prostý text, {t:…} ho escapuje: text je i v atributech a překladatel do něj může napsat `"` nebo `&`.
     */
    public function testPrekladSeEscapuje(): void
    {
        Preklad::setAdresarKatalogu(__DIR__ . '/../Jazyk/resources/preklady');
        try {
            AktualniJazyk::nastav(Jazyk::CESTINA);
            $template = new XTemplate(__DIR__ . '/resources/preklad-escapovani.xtpl');
            $template->parse('page');
            $html = $template->text('page');
        } finally {
            Preklad::setAdresarKatalogu(null);
            AktualniJazyk::reset();
        }

        self::assertStringContainsString('content="Řekl &quot;ahoj&quot; &amp; odešel"', $html);
        self::assertStringContainsString('<p>Řekl &quot;ahoj&quot; &amp; odešel</p>', $html);
    }

    public function testOne()
    {
        $template = new XTemplate(__DIR__ . '/resources/test.xtpl');
        $template->assign('name', 'Ledr');
        $template->assign('surname', 'Pálesyk');
        $template->parse('page.person');
        $template->parse('page');
        $content = $template->text('page');
        self::assertStringContainsString('Ledr', $content);
        self::assertStringContainsString('Pálesyk', $content);
    }

    public function testCacheDir()
    {
        XTemplate::cache(__DIR__ . '/cache');
        $t = new XTemplate(__DIR__ . '/resources/test2.xtpl');
        $this->assertCount(1, glob(__DIR__ . '/cache/*.php'));
        $this->assertFileDoesNotExist(__DIR__ . '/resources/test2.xtpc');
    }
}
