<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model;

use Gamecon\Jazyk\Jazyk;
use PHPUnit\Framework\TestCase;

class SmartypTest extends TestCase
{
    public function testCeskyTextDostaneCeskeUvozovky(): void
    {
        self::assertSame('<p>„Ahoj“ – řekl…</p>', \Smartyp::defaultTransform('<p>"Ahoj" - řekl...</p>'));
        self::assertSame('<p>„Ahoj“</p>', \Smartyp::defaultTransform('<p>“Ahoj”</p>', Jazyk::CESTINA));
    }

    public function testAnglickyTextDostaneAnglickeUvozovky(): void
    {
        self::assertSame('<p>“Hello” – he said…</p>', \Smartyp::defaultTransform('<p>"Hello" - he said...</p>', Jazyk::ANGLICTINA));
        self::assertSame('<p>“Hello”</p>', \Smartyp::defaultTransform('<p>“Hello”</p>', Jazyk::ANGLICTINA));
    }

    public function testMarkdownJinehoJazykaSiNesahaDoCacheCeskeho(): void
    {
        $text = '"Typografie ' . uniqid('', true) . '"';

        self::assertSame('<p>„' . substr($text, 1, -1) . '“</p>', trim((string) markdown($text)));
        self::assertSame('<p>“' . substr($text, 1, -1) . '”</p>', trim((string) markdown($text, Jazyk::ANGLICTINA)));
    }
}
