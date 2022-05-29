<?php

use Gamecon\XTemplate\XTemplate;

class XTemplateTest extends \PHPUnit\Framework\TestCase
{

    public function setUp(): void {
        mkdir(__DIR__ . '/cache');
    }

    protected function tearDown(): void {
        exec('rm -rf ' . escapeshellarg(__DIR__ . '/cache'));
    }

    public function testOne() {
        $t = new XTemplate(__DIR__ . '/resources/test.xtpl');
    }

    public function testCacheDir() {
        XTemplate::cache(__DIR__ . '/cache');
        $t = new XTemplate(__DIR__ . '/resources/test2.xtpl');
        $this->assertCount(1, glob(__DIR__ . '/cache/*.php'));
        $this->assertFileDoesNotExist(__DIR__ . '/resources/test2.xtpc');
    }

}
