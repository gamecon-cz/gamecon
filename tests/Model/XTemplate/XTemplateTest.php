<?php

use Gamecon\XTemplate\XTemplate;

class XTemplateTest extends \PHPUnit\Framework\TestCase
{

    public function setUp(): void {
        mkdir(__DIR__ . '/cache');
    }

    protected function tearDown(): void {
        exec('rm -rf ' . __DIR__ . '/cache');
        exec('rm -f ' . __DIR__ . '/resources/*.xtpc');
    }

    public function testOne() {
        $template = new XTemplate(__DIR__ . '/resources/test.xtpl');
        $template->assign('name', 'Ledr');
        $template->assign('surname', 'Pálesyk');
        $template->parse('page.person');
        $template->parse('page');
        $content = $template->text('page');
        self::assertStringContainsString('Ledr', $content);
        self::assertStringContainsString('Pálesyk', $content);
    }

    public function testCacheDir() {
        XTemplate::cache(__DIR__ . '/cache');
        $t = new XTemplate(__DIR__ . '/resources/test2.xtpl');
        $this->assertCount(1, glob(__DIR__ . '/cache/*.php'));
        $this->assertFileDoesNotExist(__DIR__ . '/resources/test2.xtpc');
    }

}
