<?php

declare(strict_types=1);

namespace Gamecon\Tests\Dev;

use Gamecon\Dev\UrlWithBasicAuth;
use PHPUnit\Framework\TestCase;

class UrlWithBasicAuthTest extends TestCase
{
    /**
     * @test
     */
    public function injectsUserAndPasswordIntoBasicHttpsUrl(): void
    {
        self::assertSame(
            'https://foo:bar@feature-x.preview.gamecon.cz/',
            UrlWithBasicAuth::inject('https://feature-x.preview.gamecon.cz/', 'foo', 'bar'),
        );
    }

    /**
     * @test
     */
    public function preservesPathQueryAndFragment(): void
    {
        self::assertSame(
            'https://foo:bar@feature-x.preview.gamecon.cz/admin/dev?x=1#h',
            UrlWithBasicAuth::inject(
                'https://feature-x.preview.gamecon.cz/admin/dev?x=1#h',
                'foo',
                'bar',
            ),
        );
    }

    /**
     * @test
     */
    public function preservesPortWhenPresent(): void
    {
        self::assertSame(
            'http://foo:bar@localhost:8080/',
            UrlWithBasicAuth::inject('http://localhost:8080/', 'foo', 'bar'),
        );
    }

    /**
     * @test
     */
    public function urlEncodesSpecialCharactersInPassword(): void
    {
        self::assertSame(
            'https://foo:p%40ss%3Aword@host/',
            UrlWithBasicAuth::inject('https://host/', 'foo', 'p@ss:word'),
        );
    }

    /**
     * @test
     */
    public function returnsUrlUnchangedWhenUserOrPasswordMissing(): void
    {
        $url = 'https://host/';
        self::assertSame($url, UrlWithBasicAuth::inject($url, '', 'bar'));
        self::assertSame($url, UrlWithBasicAuth::inject($url, 'foo', ''));
        self::assertSame($url, UrlWithBasicAuth::inject($url, '', ''));
    }
}
