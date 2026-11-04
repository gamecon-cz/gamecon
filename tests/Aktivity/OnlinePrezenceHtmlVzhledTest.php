<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Admin\VzhledAdminu;
use Gamecon\Admin\VzhledAdminuEnum;
use Gamecon\Admin\VzhledAdminuHtml;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceHtml;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractUzivatelTestDb;
use Gamecon\XTemplate\XTemplate;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Online prezence (Moje aktivity) nemá rám adminu, vzhled si proto vykresluje sama.
 */
class OnlinePrezenceHtmlVzhledTest extends AbstractUzivatelTestDb
{
    /**
     * @var array<string, mixed>
     */
    private array $session = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Without it XTemplate compiles the template next to its source, into the tracked tree
        $cacheDir = XTemplate::cache() ?: XTPL_CACHE_DIR;
        pripravCache($cacheDir);
        XTemplate::cache($cacheDir);
    }

    private function dejHtml(?VzhledAdminuEnum $vzhled): string
    {
        $vypravec = self::prihlasenyUzivatel();
        $this->session = [];
        $vzhledAdminu = new VzhledAdminu($this->session);
        if ($vzhled !== null) {
            // jako admin/index.php: vzhled stránky je ten zvolený
            $vzhledAdminu->setVzhled((int) $vypravec->id(), $vzhled);
        }
        $onlinePrezenceHtml = $vzhled === null
            ? new OnlinePrezenceHtml('', SystemoveNastaveni::zGlobals(), new Filesystem())
            : new OnlinePrezenceHtml(
                '',
                SystemoveNastaveni::zGlobals(),
                new Filesystem(),
                vzhled: $vzhled,
                vzhledAdminuHtml: new VzhledAdminuHtml($vzhledAdminu),
                nazevStranky: 'Moje aktivity',
            );

        return $onlinePrezenceHtml->dejHtmlOnlinePrezence($vypravec, []);
    }

    public function testBezVzhleduZustavaKlasickaBezPrepinace(): void
    {
        $html = $this->dejHtml(null);

        self::assertStringNotContainsString('vzhled-moderni', $html);
        self::assertStringNotContainsString('online-prezence-moderni.css', $html);
        self::assertStringNotContainsString('prepinac-vzhledu', $html);
        self::assertStringNotContainsString('op-lista', $html);
    }

    public function testKlasickyVzhledMaJenPrepinac(): void
    {
        $html = $this->dejHtml(VzhledAdminuEnum::KLASICKY);

        self::assertStringNotContainsString('vzhled-moderni', $html);
        self::assertStringNotContainsString('online-prezence-moderni.css', $html);
        self::assertStringNotContainsString('op-lista', $html);
        self::assertStringContainsString('prepinac-vzhledu', $html);
        self::assertStringContainsString('vzhled_adminu.css', $html);
        self::assertStringNotContainsString('barvy_adminu.js', $html, 'Tmavé barvy umí jen moderní vzhled');
    }

    public function testVypiseHlasky(): void
    {
        \Chyba::vyzvedniHtml(); // hlášky z dřívějška pryč
        oznameni('Administrace má teď moderní vzhled.', false);

        $html = $this->dejHtml(VzhledAdminuEnum::MODERNI);

        self::assertStringContainsString('Administrace má teď moderní vzhled.', $html);
        self::assertSame('', \Chyba::vyzvedniHtml(), 'Vypsaná hláška už nevyskočí na další stránce adminu');
    }

    public function testModerniVzhledMaListuAStyly(): void
    {
        $html = $this->dejHtml(VzhledAdminuEnum::MODERNI);

        self::assertStringContainsString('<body class="vzhled-moderni">', $html);
        self::assertStringContainsString('<strong>Moje aktivity</strong>', $html);
        self::assertStringContainsString('online-prezence-moderni.css', $html);
        self::assertStringContainsString('prepinac-vzhledu', $html);
        self::assertStringContainsString('data-barvy-adminu="tmave"', $html);
        self::assertLessThan(
            strpos($html, '<body'),
            strpos($html, 'barvy_adminu.js'),
            'Barvy se nastaví v hlavičce, než se stránka vykreslí',
        );
        self::assertLessThan(
            strpos($html, 'online-prezence-moderni.css'),
            strpos($html, 'design/online-prezence.css'),
            'Moderní styly se načítají za klasické, aby je přebily',
        );
    }
}
