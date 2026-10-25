<?php

declare(strict_types=1);

namespace Gamecon\Tests\Admin;

use Gamecon\Admin\VzhledAdminu;
use Gamecon\Admin\VzhledAdminuEnum;
use Gamecon\Admin\VzhledAdminuHtml;
use Gamecon\Tests\Db\AbstractTestDb;

class VzhledAdminuHtmlTest extends AbstractTestDb
{
    /**
     * @var array<string, mixed>
     */
    private array $session = [];

    private function vytvorUzivatele(string $suffix): int
    {
        dbQuery(<<<'SQL'
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

        self::assertStringContainsString('value="klasicky" aria-pressed="true"', $html->dejPrepinac($idUzivatele));

        $vzhledAdminu->setVzhled($idUzivatele, VzhledAdminuEnum::MODERNI);

        $prepinac = $html->dejPrepinac($idUzivatele);
        self::assertStringContainsString('value="moderni" aria-pressed="true"', $prepinac);
        self::assertStringContainsString('value="klasicky" aria-pressed="false"', $prepinac);
    }

    public function testBezOznameniPridaSkrytePole(): void
    {
        $idUzivatele = $this->vytvorUzivatele('bez_oznameni');
        $html = new VzhledAdminuHtml($this->vytvorVzhledAdminu());
        $skrytePole = 'name="' . VzhledAdminu::POST_KLIC_BEZ_OZNAMENI . '"';

        self::assertStringNotContainsString($skrytePole, $html->dejPrepinac($idUzivatele));
        self::assertStringContainsString($skrytePole, $html->dejPrepinac($idUzivatele, sOznamenim: false));
        self::assertStringNotContainsString($skrytePole, $html->dejVyber($idUzivatele));
        self::assertStringContainsString($skrytePole, $html->dejVyber($idUzivatele, sOznamenim: false));
    }

    public function testVyberJenDokudSiUzivatelNevybral(): void
    {
        $idUzivatele = $this->vytvorUzivatele('vyber');
        $vzhledAdminu = $this->vytvorVzhledAdminu();
        $html = new VzhledAdminuHtml($vzhledAdminu);

        self::assertStringContainsString('<dialog', $html->dejVyber($idUzivatele));

        $vzhledAdminu->setVzhled($idUzivatele, VzhledAdminuEnum::KLASICKY);

        self::assertSame('', $html->dejVyber($idUzivatele));
    }

    public function testOdlozenyVyberSeNeukaze(): void
    {
        $idUzivatele = $this->vytvorUzivatele('odlozeny');
        $vzhledAdminu = $this->vytvorVzhledAdminu();
        $vzhledAdminu->zpracujVolbu($idUzivatele, VzhledAdminu::VOLBA_POZDEJI);

        self::assertSame('', (new VzhledAdminuHtml($vzhledAdminu))->dejVyber($idUzivatele));
    }
}
