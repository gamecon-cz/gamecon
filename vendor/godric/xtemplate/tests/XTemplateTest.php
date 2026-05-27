<?php

class XTemplateTest extends PHPUnit_Framework_TestCase {

  function setUp() {
    mkdir(__DIR__ . '/cache');
  }

  function testOne() {
    $t = new XTemplate(__DIR__ . '/resources/test.xtpl');
  }

  function testCacheDir() {
    XTemplate::cache(__DIR__ . '/cache');
    $t = new XTemplate(__DIR__ . '/resources/test2.xtpl');
    $this->assertCount(1, glob(__DIR__ . '/cache/*.php'));
    $this->assertFileNotExists(__DIR__ . '/resources/test2.xtpc');
  }

  function tearDown() {
    exec('rm -rf ' . escapeshellarg(__DIR__ . '/cache'));
  }

}
