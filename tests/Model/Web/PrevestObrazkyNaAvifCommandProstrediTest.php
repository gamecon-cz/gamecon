<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use App\Command\PrevestObrazkyNaAvifCommand;
use Gamecon\Prostredi\Prostredi;
use PHPUnit\Framework\TestCase;

class PrevestObrazkyNaAvifCommandProstrediTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider provideProstrediAdresareAAdresy
     */
    public function zapisJdeJenKdyzProstrediAdresarAAdresaWebuSedi(Prostredi $prostredi, string $adresarProjektu, string $urlWebu, bool $souhlasi): void
    {
        self::assertSame($souhlasi, PrevestObrazkyNaAvifCommand::getNesouladProstredi($prostredi, $adresarProjektu, $urlWebu) === null);
    }

    /**
     * @return iterable<string, array{Prostredi, string, string, bool}>
     */
    public static function provideProstrediAdresareAAdresy(): iterable
    {
        yield 'beta v adresáři beta' => [Prostredi::Beta, '/srv/www/beta', 'https://beta.gamecon.cz', true];
        yield 'beta s lomítkem na konci' => [Prostredi::Beta, '/srv/www/beta/', 'https://beta.gamecon.cz', true];
        yield 'ostrá v adresáři ostré' => [Prostredi::Production, '/srv/www/ostra', 'https://gamecon.cz', true];
        yield 'beta se hlásí jako ostrá (prázdný SERVER_NAME)' => [Prostredi::Production, '/srv/www/beta', 'https://gamecon.cz', false];
        yield 'ostrá se hlásí jako beta' => [Prostredi::Beta, '/srv/www/ostra', 'https://beta.gamecon.cz', false];
        yield 'adresář jen začínající beta' => [Prostredi::Beta, '/srv/www/beta-oprava', 'https://beta.gamecon.cz', false];
        yield 'ostrá bez nastaveni-server.php se hlásí jako lokální' => [Prostredi::Locale, '/srv/www/ostra', 'https://gamecon.cz', false];
        yield 'beta bez nastaveni-server.php se hlásí jako lokální' => [Prostredi::Locale, '/srv/www/beta', 'https://beta.gamecon.cz', false];
        yield 'beta s nastavením ostré' => [Prostredi::Beta, '/srv/www/beta', 'https://gamecon.cz', false];
        yield 'lokální kopie' => [Prostredi::Locale, '/var/www/html/gamecon', 'http://localhost/web', true];
        yield 'preview' => [Prostredi::Preview, '/var/www/html/gamecon', 'https://nejaka-vetev.preview.gamecon.cz', true];
        yield 'neznámá adresa webu u ostré' => [Prostredi::Production, '/srv/www/ostra', 'https://jiny.example.org', false];
        yield 'neznámá adresa webu u bety' => [Prostredi::Beta, '/srv/www/beta', 'https://jiny.example.org', false];
        yield 'lokální web vývojáře na vlastní adrese' => [Prostredi::Locale, '/home/vyvojar/gamecon', 'http://gamecon.test', true];
        yield 'lokální prostředí s adresou z domény GameConu' => [Prostredi::Locale, '/srv/www/ostra', 'https://www.gamecon.cz', false];
        yield 'lokální prostředí s adresou archivu' => [Prostredi::Locale, '/srv/www/2024', 'https://2024.gamecon.cz', false];
        yield 'archivní ročník se nezapisuje' => [Prostredi::Production, '/srv/www/2024', 'https://2024.gamecon.cz', false];
    }
}
