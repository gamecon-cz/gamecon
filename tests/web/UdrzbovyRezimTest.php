<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use PHPUnit\Framework\TestCase;

/**
 * Údržbový režim zapíná soubor `maintenance.html` v adresáři webu, viz dokumentace/nasazeni.md. Testuje se proti
 * Apache ve složení jako na ostré ({@see ApacheJakoNaOstre}), kde admin leží na vlastním jménu s vlastním kořenem.
 */
class UdrzbovyRezimTest extends TestCase
{
    private static ApacheJakoNaOstre $apache;

    public static function setUpBeforeClass(): void
    {
        self::$apache = ApacheJakoNaOstre::spust();
    }

    public static function tearDownAfterClass(): void
    {
        self::$apache->zastav();
    }

    protected function tearDown(): void
    {
        self::$apache->vypniUdrzbu();
    }

    /**
     * @test
     *
     * @dataProvider provideAdresyBezUdrzby
     */
    public function bezUdrzbyFungujeWebAdminIStatickeSoubory(string $host, string $cesta, string $ocekavaneTelo): void
    {
        $odpoved = self::$apache->pozadavek($host, $cesta);

        self::assertSame(200, $odpoved['status']);
        self::assertSame($ocekavaneTelo, $odpoved['telo']);
        self::assertArrayNotHasKey('retry-after', $odpoved['hlavicky'], 'Retry-After patří jen k 503');
    }

    public static function provideAdresyBezUdrzby(): iterable
    {
        yield 'web, úvodní strana' => [ApacheJakoNaOstre::HOST_WEBU, '/', ApacheJakoNaOstre::TELO_WEBU];
        yield 'web, stránka' => [ApacheJakoNaOstre::HOST_WEBU, '/program', ApacheJakoNaOstre::TELO_WEBU];
        yield 'web přímo pod /web' => [ApacheJakoNaOstre::HOST_WEBU, '/web/program', ApacheJakoNaOstre::TELO_WEBU];
        yield 'admin na vlastním jménu' => [ApacheJakoNaOstre::HOST_ADMINU, '/', ApacheJakoNaOstre::TELO_ADMINU];
        yield 'admin pod /admin na jménu webu' => [ApacheJakoNaOstre::HOST_WEBU, '/admin/?req=', ApacheJakoNaOstre::TELO_ADMINU];
        yield 'cron' => [ApacheJakoNaOstre::HOST_ADMINU, '/cron.php', ApacheJakoNaOstre::TELO_CRONU];
        yield 'soubor z cache' => [ApacheJakoNaOstre::HOST_WEBU, '/cache/public/styl.css', ApacheJakoNaOstre::TELO_SOUBORU_Z_CACHE];
        yield 'stránka údržby sama se bez údržby na webu nenabízí' => [ApacheJakoNaOstre::HOST_WEBU, '/maintenance.off.html', ApacheJakoNaOstre::TELO_WEBU];
        yield 'admin na vlastním jménu nabízí nasazenou stránku údržby i bez údržby, workflow podle ní pozná, že je nasazená' => [ApacheJakoNaOstre::HOST_ADMINU, '/maintenance.off.html', self::getStrankaUdrzby()];
    }

    /**
     * `ErrorDocument` z .htaccess platí jen za údržby; jinak by přebil obecnou stránku serveru (ansible), kterou Apache
     * ukáže, když neběží PHP-FPM, a při pádu FPM by návštěvník dostal holou chybu o chybě `ErrorDocument`.
     *
     * @test
     *
     * @dataProvider provideHostyWebuAAdminu
     */
    public function bezUdrzbyPadPhpFpmUkazeObecnouStrankuServeru(string $host): void
    {
        $odpoved = self::$apache->pozadavek($host, '/' . ApacheJakoNaOstre::SKRIPT_BEZ_PHP_FPM);

        self::assertSame(503, $odpoved['status']);
        self::assertSame(ApacheJakoNaOstre::TELO_OBECNE_STRANKY_SERVERU, $odpoved['telo']);
    }

    /**
     * Obsah stránky údržby, ne obecné stránky serveru, kterou ansible dává na `/maintenance.html`.
     *
     * @test
     *
     * @dataProvider provideAdresyZaUdrzby
     */
    public function sPrepinacemOdpovidaKazdyPozadavekNaWeb503SeStrankouUdrzby(string $host, string $metoda, string $cesta): void
    {
        self::$apache->zapniUdrzbuSouborem();

        $odpoved = self::$apache->pozadavek($host, $cesta, $metoda);

        self::assertSame(503, $odpoved['status'], "{$metoda} {$host}{$cesta}");
        self::assertSame(['300'], $odpoved['hlavicky']['retry-after'] ?? [], "{$metoda} {$host}{$cesta} bez Retry-After");
        if ($metoda !== 'HEAD') {
            self::assertSame(self::getStrankaUdrzby(), $odpoved['telo'], "{$metoda} {$host}{$cesta} neukázal stránku údržby");
        }
    }

    public static function provideAdresyZaUdrzby(): iterable
    {
        $web = ApacheJakoNaOstre::HOST_WEBU;
        $admin = ApacheJakoNaOstre::HOST_ADMINU;

        yield 'web, úvodní strana' => [$web, 'GET', '/'];
        yield 'web, stránka' => [$web, 'GET', '/program'];
        yield 'web, POST' => [$web, 'POST', '/prihlaseni'];
        yield 'web, HEAD' => [$web, 'HEAD', '/'];
        yield 'web přímo pod /web' => [$web, 'GET', '/web/program'];
        yield 'soubor z cache' => [$web, 'GET', '/cache/public/styl.css'];
        yield 'složka deploy mimo admin' => [$web, 'POST', '/deploy/migrace.php'];
        yield 'neexistující adresa pod deploy na jménu adminu' => [$admin, 'GET', '/deploy/neexistuje'];
        yield 'neexistující adresa pod deploy pod /admin na jménu webu' => [$web, 'GET', '/admin/deploy/neexistuje'];
        // s příponou se adresa nepřepisuje na index.php, takže ji před údržbou nezachrání nic než podmínka na existující soubor
        yield 'neexistující skript pod deploy na jménu adminu' => [$admin, 'GET', '/deploy/neexistuje.php'];
        yield 'neexistující skript pod deploy pod /admin na jménu webu' => [$web, 'GET', '/admin/deploy/neexistuje.php'];
        yield 'brána previews a archivů se ptá adminu' => [$admin, 'GET', '/api/preview-vstup?nonce=0123456789abcdef0123456789abcdef'];
        yield 'admin pod /admin na jménu webu' => [$web, 'GET', '/admin/?req='];
        yield 'admin na vlastním jménu' => [$admin, 'GET', '/'];
        yield 'admin, stránka' => [$admin, 'GET', '/uzivatel'];
        yield 'admin, POST' => [$admin, 'POST', '/uzivatel'];
        yield 'admin, soubor, který neexistuje' => [$admin, 'GET', '/neexistuje.css'];
        yield 'cron' => [$admin, 'GET', '/cron.php'];
    }

    /**
     * Nasazení po nahrání souborů volá admin/deploy/* (smazání cache, migrace, cache programu) a bez toho by za údržby skončilo červeně.
     *
     * @test
     *
     * @dataProvider provideAdresyNasazeni
     */
    public function zaUdrzbyProchaziNasazeniAdminu(string $host, string $cesta): void
    {
        self::$apache->zapniUdrzbuSouborem();

        $odpoved = self::$apache->pozadavek($host, $cesta, 'POST');

        self::assertSame(200, $odpoved['status']);
        self::assertSame(ApacheJakoNaOstre::TELO_NASAZENI, $odpoved['telo']);
        self::assertArrayNotHasKey('retry-after', $odpoved['hlavicky'], 'Retry-After patří jen k 503');
    }

    public static function provideAdresyNasazeni(): iterable
    {
        yield 'admin na vlastním jménu' => [ApacheJakoNaOstre::HOST_ADMINU, '/deploy/migrace.php'];
        yield 'admin pod /admin na jménu webu' => [ApacheJakoNaOstre::HOST_WEBU, '/admin/deploy/migrace.php'];
    }

    /**
     * Na ostré jde `.php` přes PHP-FPM jako handler. Skript v deploy/ musí za údržby dojít až k němu: stejný 503 by dal i
     * pád FPM, takže se pozná podle chyby handleru v logu Apache.
     *
     * @test
     */
    public function zaUdrzbyDojdeSkriptDeployAzKHandleruPhpFpm(): void
    {
        $dojdeKHandleru = static function (string $cesta): bool {
            $delkaLoguPred = strlen(self::$apache->getChybovyLog());
            self::$apache->pozadavek(ApacheJakoNaOstre::HOST_ADMINU, $cesta, 'POST');

            return stripos(substr(self::$apache->getChybovyLog(), $delkaLoguPred), 'fcgi') !== false;
        };
        $skriptVDeploy = '/deploy/' . ApacheJakoNaOstre::SKRIPT_BEZ_PHP_FPM;
        $skriptMimoDeploy = '/' . ApacheJakoNaOstre::SKRIPT_BEZ_PHP_FPM;

        // kontroly se stejným handlerem a hostem: bez údržby handler zalogování dá, a údržba ho zastaví všude mimo deploy
        self::assertTrue($dojdeKHandleru($skriptMimoDeploy), 'Kontrola: bez údržby by se handler měl zalogovat');
        self::$apache->zapniUdrzbuSouborem();
        self::assertFalse($dojdeKHandleru($skriptMimoDeploy), 'Kontrola: údržba má skript mimo deploy zastavit před handlerem');

        self::assertTrue($dojdeKHandleru($skriptVDeploy), 'Skript v deploy/ nedošel za údržby k handleru PHP-FPM');
    }

    /**
     * @test
     *
     * @dataProvider provideHostyWebuAAdminu
     */
    public function odkazNaStrankuUdrzbyZapneUdrzbuStejneJakoSoubor(string $host): void
    {
        self::$apache->zapniUdrzbuOdkazem();

        $odpoved = self::$apache->pozadavek($host, '/');

        self::assertSame(503, $odpoved['status']);
        self::assertSame(self::getStrankaUdrzby(), $odpoved['telo']);
    }

    /**
     * @test
     *
     * @dataProvider provideHostyWebuAAdminu
     */
    public function poSmazaniSouboruWebHnedFungujeBezRestartuApache(string $host): void
    {
        self::$apache->zapniUdrzbuSouborem();
        self::assertSame(503, self::$apache->pozadavek($host, '/')['status']);

        self::$apache->vypniUdrzbu();

        self::assertSame(200, self::$apache->pozadavek($host, '/')['status']);
    }

    public static function provideHostyWebuAAdminu(): iterable
    {
        yield 'web' => [ApacheJakoNaOstre::HOST_WEBU];
        yield 'admin' => [ApacheJakoNaOstre::HOST_ADMINU];
    }

    /**
     * Pravidla přepisování se do podsložky s vlastním .htaccess nedědí, takže bez bloku údržby v něm by složka
     * údržbu obešla (admin a soubory z cache to dělaly, protože mají vlastní RewriteEngine).
     *
     * @test
     */
    public function kazdyHtaccessSPrepisovanimMaNaZacatkuBlokUdrzby(): void
    {
        $htaccessy = self::najdiHtaccessySPrepisovanim();

        self::assertArrayHasKey('.htaccess', $htaccessy, 'Hledání nenašlo ani kořenový .htaccess');
        $chyby = [];
        foreach ($htaccessy as $soubor => $obsah) {
            // zakomentovaný blok nic nedělá
            $bezKomentaru = (string) preg_replace('~^\s*#.*$~m', '', $obsah);
            $povinneCasti = [
                'podmínku na přepínač před pravidlem' => '~^RewriteCond\s+%\{DOCUMENT_ROOT\}/maintenance\.html\s+-f\b~m',
                'výjimku pro stránku údržby'          => '~^' . preg_quote('RewriteRule ^maintenance\.off\.html$ - [L]', '~') . '$~m',
                'pravidlo, které odpoví 503'          => '~^' . preg_quote('RewriteRule ^ - [R=503,L]', '~') . '$~m',
                'ErrorDocument jen za údržby'         => '~<If\s+"[^"]*maintenance\.html[^"]*">\s*ErrorDocument\s+503\s+/maintenance\.off\.html\s*</If>~',
            ];
            $povinneCasti['hlavičku Retry-After jen pro 503'] = '~^\s*Header\s+always\s+setifempty\s+Retry-After\s+"300"\s+"expr=%\{REQUEST_STATUS\}\s*==\s*503"~m';
            foreach ($povinneCasti as $popis => $vzor) {
                if (preg_match($vzor, $bezKomentaru) !== 1) {
                    $chyby[] = "{$soubor} nemá {$popis}";
                }
            }
            // blok je před každým jiným pravidlem a každému z jeho dvou pravidel předchází podmínka
            $radky = array_values(array_filter(array_map('trim', explode("\n", $bezKomentaru)), static fn (string $radek) => $radek !== ''));
            $pravidla = array_keys(array_filter($radky, static fn (string $radek) => str_starts_with($radek, 'RewriteRule ')));
            $dvaPrvniRadky = [$radky[$pravidla[0] ?? -1] ?? '', $radky[$pravidla[1] ?? -1] ?? ''];
            if ($dvaPrvniRadky !== ['RewriteRule ^maintenance\.off\.html$ - [L]', 'RewriteRule ^ - [R=503,L]']) {
                $chyby[] = "{$soubor} nemá blok údržby jako první dvě pravidla přepisování";
            }
            foreach (array_slice($pravidla, 0, 2) as $poradi) {
                $podminky = [];
                for ($predchozi = $poradi - 1; $predchozi >= 0 && str_starts_with($radky[$predchozi], 'RewriteCond '); --$predchozi) {
                    array_unshift($podminky, $radky[$predchozi]);
                }
                if (preg_grep('~maintenance\.html\s+-f\b~', $podminky) === []) {
                    $chyby[] = "{$soubor} nemá před pravidlem '{$radky[$poradi]}' podmínku na přepínač";
                }
                // [OR] na poslední podmínce by se spojil s pravidlem a rozbil skupiny podmínek
                if (str_contains((string) end($podminky), '[OR]')) {
                    $chyby[] = "{$soubor} má [OR] na poslední podmínce před pravidlem '{$radky[$poradi]}'";
                }
            }
        }

        self::assertSame([], $chyby, 'Blok údržby z kořenového .htaccess patří do každého .htaccess s RewriteEngine, viz dokumentace/nasazeni.md');
    }

    /**
     * Za údržby se nic dalšího neposkytuje, takže stránka nesmí potřebovat žádný vnější soubor.
     *
     * @test
     */
    public function strankaUdrzbyJeSamostatna(): void
    {
        self::assertDoesNotMatchRegularExpression('~<link\b|<script\b|<img\b|\ssrc\s*=|\shref\s*=|@import|url\(~i', self::getStrankaUdrzby());
    }

    /**
     * Admin na vlastním jménu má vlastní kořen, kde stránka údržby z kořene webu není vidět.
     *
     * @test
     */
    public function adminMaStejnouStrankuUdrzbyJakoWeb(): void
    {
        self::assertSame(self::getStrankaUdrzby(), (string) file_get_contents(dirname(__DIR__, 2) . '/admin/maintenance.off.html'));
    }

    private static function getStrankaUdrzby(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/maintenance.off.html');
    }

    /**
     * @return array<string, string> obsah podle cesty od kořene projektu
     */
    private static function najdiHtaccessySPrepisovanim(): array
    {
        $koren = dirname(__DIR__, 2);
        $nalezene = [];
        $projdi = static function (string $adresar) use (&$projdi, &$nalezene, $koren): void {
            foreach (scandir($adresar) as $polozka) {
                if ($polozka === '.' || $polozka === '..') {
                    continue;
                }
                $cesta = "{$adresar}/{$polozka}";
                $relativni = substr($cesta, strlen($koren) + 1);
                if (is_link($cesta)) {
                    continue;
                }
                if (is_dir($cesta)) {
                    if (! in_array($relativni, ['.git', 'vendor', 'node_modules', 'ui/node_modules', 'symfony/var', 'symfony/vendor', 'logy', 'backup', 'cache/private', 'web/soubory'], true)) {
                        $projdi($cesta);
                    }
                    continue;
                }
                if ($polozka === '.htaccess') {
                    $obsah = (string) file_get_contents($cesta);
                    if (preg_match('~^\s*RewriteEngine\s+on\s*$~mi', $obsah) === 1) {
                        $nalezene[$relativni] = $obsah;
                    }
                }
            }
        };
        $projdi($koren);

        return $nalezene;
    }
}
