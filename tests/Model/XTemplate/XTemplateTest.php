<?php

declare(strict_types=1);

use Gamecon\Jazyk\AktualniJazyk;
use Gamecon\Jazyk\Jazyk;
use Gamecon\XTemplate\XTemplate;
use Symfony\Component\Filesystem\Filesystem;

class XTemplateTest extends PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        (new Filesystem())->mkdir(__DIR__ . '/cache', 0775);
    }

    protected function tearDown(): void
    {
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
