<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

require_once __DIR__ . '/../../admin/scripts/admin-menu.php';

class EditaceStranekVAdminuTest extends AbstractTestWeb
{
    private const int ID_STRANKY = 9001;
    private const string URL_STRANKY = 'ukazkova-stranka';
    private const string STRANKA_ADMINU = 'web';

    /**
     * @return array<int, string|array{0: string, 1: array<int, mixed>}>
     */
    protected static function getSetUpBeforeClassInitQueries(): array
    {
        return [
            ...parent::getSetUpBeforeClassInitQueries(),
            [
                <<<'SQL'
INSERT INTO stranky(id_stranky, url_stranky, obsah, poradi)
VALUES ($0, $1, 'Vymyšlený text ![logo](soubory/obsah/ukazka.png)', 0)
SQL,
                [self::ID_STRANKY, self::URL_STRANKY],
            ],
        ];
    }

    /**
     * @test
     */
    public function muzuSiOtevritExistujiciStrankuKEditaci()
    {
        $telo = $this->fetchAdminPage('admin/' . self::STRANKA_ADMINU . '/editace-stranek?id=' . self::ID_STRANKY);

        self::assertStringContainsString('value="' . self::URL_STRANKY . '"', $telo);
        self::assertStringContainsString('QR kód s odkazem', $telo);
        // v novém okně, ať se neztratí rozepsaný text
        self::assertMatchesRegularExpression(
            '~<a href="[^"]*/' . self::URL_STRANKY . '" target="_blank" rel="noopener"><i class="fa fa-external-link" aria-hidden="true"></i> Stránka na webu</a>~',
            $telo,
        );
        $this->assertPodmenuVedeNaStrankyWebu($telo);
    }

    /**
     * Administrace má `<base>` na svůj adresář, relativní adresa obrázku by v náhledu vedla do ní.
     *
     * @test
     */
    public function obrazekVNahleduTextuVedeNaWebNeDoAdministrace()
    {
        $telo = $this->fetchAdminPage('admin/' . self::STRANKA_ADMINU . '/editace-stranek?id=' . self::ID_STRANKY);

        self::assertMatchesRegularExpression('~class="dbffMdtext">\s*<p>Vymyšlený text <img src="[^"]*/soubory/obsah/ukazka\.png"~u', $telo);
        self::assertStringNotContainsString('<img src="soubory/obsah/ukazka.png"', $telo);
        self::assertStringContainsString('.dbffMdtext img { max-width: 100%; max-height: 6em; }', $telo);
    }

    /**
     * @test
     */
    public function novaStrankaJesteNemaNaCoOdkazovat()
    {
        $telo = $this->fetchAdminPage('admin/' . self::STRANKA_ADMINU . '/editace-stranek?akce=nova');

        self::assertStringNotContainsString('odkaz-na-web', $telo);
    }

    /**
     * @test
     */
    public function podmenuNaNoveStranceVedeNaStrankyWebu()
    {
        $telo = $this->fetchAdminPage('admin/' . self::STRANKA_ADMINU . '/editace-stranek?akce=nova');

        $this->assertPodmenuVedeNaStrankyWebu($telo);
    }

    private function assertPodmenuVedeNaStrankyWebu(string $telo): void
    {
        $urlPodmenu = array_keys((new \AdminMenu(__DIR__ . '/../../admin/scripts/modules/' . self::STRANKA_ADMINU . '/', true))->pole());
        self::assertNotEmpty($urlPodmenu);
        foreach ($urlPodmenu as $url) {
            $ocekavanyOdkaz = $url === self::STRANKA_ADMINU
                ? $url
                : self::STRANKA_ADMINU . '/' . $url;
            self::assertStringContainsString('href="' . $ocekavanyOdkaz . '"', $telo);
            self::assertStringNotContainsString('href="/' . $url . '"', $telo);
        }
    }
}
