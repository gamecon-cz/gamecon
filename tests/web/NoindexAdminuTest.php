<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Login\Login;

class NoindexAdminuTest extends AbstractTestWeb
{
    /**
     * @test
     */
    public function prihlasovaciStrankaAdminuNemaBytVeVyhledavaci(): void
    {
        $odpoved = $this->stahniVerejnouStranku('admin/?req=');

        self::assertSame(200, $odpoved['status']);
        self::assertStringContainsString(
            'name="' . Login::PASSWORD_INPUT_NAME . '"',
            $odpoved['telo'],
            'Odpověď není přihlašovací stránka adminu',
        );
        self::assertSame(['noindex, nofollow'], $odpoved['hlavicky']['x-robots-tag'] ?? []);
    }

    /**
     * Whatever the admin host answers: a page of the admin, its API, a script that does not go through index.php,
     * a static file and the 404 of Apache.
     *
     * @test
     *
     * @dataProvider provideDalsiOdpovediAdminu
     */
    public function dalsiOdpovediAdminuNemajiBytVeVyhledavaci(string $cesta): void
    {
        $odpoved = $this->stahniVerejnouStranku($cesta);

        self::assertSame(['noindex, nofollow'], $odpoved['hlavicky']['x-robots-tag'] ?? [], "Adresa {$cesta} nemá noindex");
    }

    public static function provideDalsiOdpovediAdminu(): array
    {
        return [
            'API adminu'                        => ['admin/api/neexistuje'],
            'skript mimo index.php'             => ['admin/pickard.php'],
            'staticky soubor'                   => ['admin/files/design/hint.css'],
            'soubor, ktery neexistuje (Apache)' => ['admin/neexistuje.css'],
            'zakazany adresar (403)'            => ['admin/cron/anonymizace_databaze.php'],
        ];
    }

    /**
     * @test
     */
    public function verejnyWebNoindexNeposila(): void
    {
        $odpoved = $this->stahniVerejnouStranku('web/program');

        self::assertSame(200, $odpoved['status']);
        self::assertArrayNotHasKey('x-robots-tag', $odpoved['hlavicky']);
    }
}
