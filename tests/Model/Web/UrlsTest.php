<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Web\Urls;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UrlsTest extends TestCase
{
    #[DataProvider('provideCestyPozadavku')]
    public function testCestaNikdyNevedeNaJinyWeb(string $requestUri, string $ocekavanaCesta): void
    {
        self::assertSame($ocekavanaCesta, Urls::getCestaNaTomtoWebu($requestUri));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function provideCestyPozadavku(): array
    {
        return [
            'běžná stránka s dotazem'       => ['/admin/uzivatel?id=1', '/admin/uzivatel?id=1'],
            'kořen webu'                    => ['/', '/'],
            'prázdný požadavek'             => ['', '/'],
            'adresa bez schématu'           => ['//jiny.web/x', '/jiny.web/x'],
            'zpětné lomítko'                => ['/\\jiny.web', '/jiny.web'],
            'tabulátor mezi lomítky'        => ["/\t//jiny.web", '/jiny.web'],
            'nový řádek a mezera'           => ["/\n /jiny.web", '/jiny.web'],
            'řídicí znak'                   => ["/\x00//jiny.web", '/jiny.web'],
            'požadavek s celou adresou'     => ['http://jiny.web/x', '/http://jiny.web/x'],
            'lomítka uvnitř cesty zůstanou' => ['/admin//uzivatel', '/admin//uzivatel'],
        ];
    }
}
