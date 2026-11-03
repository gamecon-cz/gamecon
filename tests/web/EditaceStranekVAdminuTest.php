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
VALUES ($0, $1, 'Vymyšlený text', 0)
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
        $this->assertPodmenuVedeNaStrankyWebu($telo);
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
