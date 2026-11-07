<?php

declare(strict_types=1);

namespace Gamecon\Tests\Admin;

use Gamecon\Admin\VzhledAdminu;
use Gamecon\Admin\VzhledAdminuEnum;
use Gamecon\Admin\VzhledAdminuHtml;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\XTemplate\XTemplate;

class VzhledAdminuHtmlTest extends AbstractTestDb
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

    private function vytvorUzivatele(string $suffix): int
    {
        dbExecute(<<<'SQL'
INSERT INTO uzivatele_hodnoty SET
    login_uzivatele = $0,
    email1_uzivatele = $1,
    jmeno_uzivatele = 'Test',
    prijmeni_uzivatele = 'VzhledAdminuHtml'
SQL,
            [
                0 => 'test_vzhled_html_' . $suffix,
                1 => 'test.vzhled.html.' . $suffix . '@example.org',
            ],
        );

        return (int) dbInsertId();
    }

    private function vytvorVzhledAdminu(): VzhledAdminu
    {
        $this->session = [];

        return new VzhledAdminu($this->session);
    }

    public function testPrepinacUkazujeZvolenyVzhled(): void
    {
        $idUzivatele = $this->vytvorUzivatele('prepinac');
        $vzhledAdminu = $this->vytvorVzhledAdminu();
        $html = new VzhledAdminuHtml($vzhledAdminu);

        self::assertStringContainsString('value="klasicky" aria-pressed="true"', $html->getPrepinacHtml($idUzivatele));

        $vzhledAdminu->setVzhled($idUzivatele, VzhledAdminuEnum::MODERNI);

        $prepinac = $html->getPrepinacHtml($idUzivatele);
        self::assertStringContainsString('value="moderni" aria-pressed="true"', $prepinac);
        self::assertStringContainsString('value="klasicky" aria-pressed="false"', $prepinac);
    }

    public function testBarvyNabiziJenModerniVzhled(): void
    {
        $idUzivatele = $this->vytvorUzivatele('barvy');
        $vzhledAdminu = $this->vytvorVzhledAdminu();
        $html = new VzhledAdminuHtml($vzhledAdminu);

        self::assertStringNotContainsString('data-barvy-adminu', $html->getPrepinacHtml($idUzivatele));

        $vzhledAdminu->setVzhled($idUzivatele, VzhledAdminuEnum::MODERNI);

        $prepinac = $html->getPrepinacHtml($idUzivatele);
        foreach (['svetle', 'tmave', ''] as $barvy) {
            self::assertStringContainsString('data-barvy-adminu="' . $barvy . '"', $prepinac);
        }
        self::assertStringNotContainsString(
            'type="submit" data-barvy-adminu',
            $prepinac,
            'Barvy si pamatuje prohlížeč, tlačítka formulář vzhledu neodesílají',
        );
    }

    public function testVyberJenDokudSiUzivatelNevybral(): void
    {
        $idUzivatele = $this->vytvorUzivatele('vyber');
        $vzhledAdminu = $this->vytvorVzhledAdminu();
        $html = new VzhledAdminuHtml($vzhledAdminu);

        self::assertStringContainsString('<dialog', $html->getVyberHtml($idUzivatele));

        $vzhledAdminu->setVzhled($idUzivatele, VzhledAdminuEnum::KLASICKY);

        self::assertSame('', $html->getVyberHtml($idUzivatele));
    }

    public function testOdlozenyVyberSeNeukaze(): void
    {
        $idUzivatele = $this->vytvorUzivatele('odlozeny');
        $vzhledAdminu = $this->vytvorVzhledAdminu();
        $vzhledAdminu->zpracujVolbu($idUzivatele, VzhledAdminu::VOLBA_POZDEJI);

        self::assertSame('', (new VzhledAdminuHtml($vzhledAdminu))->getVyberHtml($idUzivatele));
    }
}
