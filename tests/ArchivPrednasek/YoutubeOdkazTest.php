<?php

declare(strict_types=1);

namespace Gamecon\Tests\ArchivPrednasek;

use Gamecon\ArchivPrednasek\YoutubeOdkaz;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class YoutubeOdkazTest extends TestCase
{
    /**
     * @return array<string, array{string, string|null}>
     */
    public static function provideOdkazy(): array
    {
        return [
            'krátký odkaz se sledováním' => ['https://youtu.be/-7-8Ax6sDuQ?si=EcJ1fZ1PYqAOVOYh', '-7-8Ax6sDuQ'],
            'mezera na konci'            => ['https://youtu.be/qrGYVOsfodk ', 'qrGYVOsfodk'],
            'mezera uprostřed'           => ['https://youtu.be/ _boOoe-YYOA', '_boOoe-YYOA'],
            'watch s dalšími parametry'  => ['https://www.youtube.com/watch?feature=share&v=FqzndwNwa3k&t=42s', 'FqzndwNwa3k'],
            'mobilní'                    => ['https://m.youtube.com/watch?v=FqzndwNwa3k', 'FqzndwNwa3k'],
            'bez schématu'               => ['youtube.com/watch?v=FqzndwNwa3k', 'FqzndwNwa3k'],
            'embed'                      => ['https://www.youtube.com/embed/FqzndwNwa3k', 'FqzndwNwa3k'],
            'nocookie'                   => ['https://www.youtube-nocookie.com/embed/FqzndwNwa3k?autoplay=1', 'FqzndwNwa3k'],
            'shorts'                     => ['https://youtube.com/shorts/FqzndwNwa3k?si=x', 'FqzndwNwa3k'],
            'live'                       => ['https://www.youtube.com/live/FqzndwNwa3k', 'FqzndwNwa3k'],
            'holé id'                    => ['FqzndwNwa3k', 'FqzndwNwa3k'],
            'prázdné'                    => ['  ', null],
            'text z tabulky'             => ['záznam není', null],
            'jiný web'                   => ['https://vimeo.com/123456789', null],
            'kanál, ne video'            => ['https://www.youtube.com/@gamecon', null],
            'watch bez videa'            => ['https://www.youtube.com/watch?list=PL123', null],
            'krátké id'                  => ['https://youtu.be/abc', null],
            'id s nepovoleným znakem'    => ['https://youtu.be/FqzndwNwa3!', null],
            // parse_str() dekóduje %0A a $ ve vzoru by nový řádek na konci pustil (12 znaků do VARCHAR(11))
            'id s novým řádkem v adrese' => ['https://youtube.com/watch?v=FqzndwNwa3k%0A', null],
            'podvržená doména'           => ['https://youtube.com.example.org/watch?v=FqzndwNwa3k', null],
        ];
    }

    #[DataProvider('provideOdkazy')]
    public function testVytahneIdVidea(string $odkaz, ?string $ocekavaneId): void
    {
        self::assertSame($ocekavaneId, YoutubeOdkaz::getIdZOdkazu($odkaz));
    }

    public function testSlozeneAdresy(): void
    {
        self::assertSame('https://www.youtube.com/watch?v=-7-8Ax6sDuQ', YoutubeOdkaz::getUrlVidea('-7-8Ax6sDuQ'));
        self::assertSame('https://www.youtube-nocookie.com/embed/-7-8Ax6sDuQ?autoplay=1', YoutubeOdkaz::getUrlPrehravace('-7-8Ax6sDuQ'));
        self::assertSame('https://i.ytimg.com/vi/-7-8Ax6sDuQ/hqdefault.jpg', YoutubeOdkaz::getUrlNahledu('-7-8Ax6sDuQ'));
    }
}
