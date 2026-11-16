<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Apache of its own on a free port, laid out like the production server, so the .htaccess files of the project run
 * under the conditions they meet there without touching the Apache that the other web tests use.
 *
 * The layout and the directives of the default vhost repeat ansible (`roles/webserver/templates/vhost.conf.j2`,
 * `tasks/vhost_links.yaml`): `VirtualDocumentRoot /srv/www/%0`, `gamecon.cz` a link to the site, `admin.gamecon.cz` a link
 * to its `admin/` (so the document root of the admin is not the document root of the site), and the `Alias` of a generic
 * maintenance page under the name `/maintenance.html`.
 */
class ApacheJakoNaOstre
{
    public const string HOST_WEBU = 'gamecon.cz';
    public const string HOST_ADMINU = 'admin.gamecon.cz';

    public const string TELO_WEBU = 'STRANKA-WEBU';
    public const string TELO_ADMINU = 'STRANKA-ADMINU';
    public const string TELO_CRONU = 'CRON';
    public const string TELO_NASAZENI = 'NASAZENI-ADMINU';
    public const string TELO_SOUBORU_Z_CACHE = 'SOUBOR-Z-CACHE';
    public const string TELO_OBECNE_STRANKY_SERVERU = 'OBECNA-STRANKA-SERVERU';
    public const string TELO_FRONT_CONTROLLERU_SYMFONY = 'FRONT-CONTROLLER-SYMFONY';
    public const string TELO_SOUBORU_VE_STROMU_SYMFONY = 'SOUBOR-VE-STROMU-SYMFONY';

    /**
     * PHP skript, který Apache předá FPM, jež neběží, takže na něj Apache odpoví sám `503`.
     */
    public const string SKRIPT_BEZ_PHP_FPM = 'fpm-nebezi.php';

    private const array MODULY = [
        'mpm_prefork',
        'authz_core',
        'access_compat',
        'mime',
        'dir',
        'alias',
        'rewrite',
        'headers',
        'setenvif',
        'vhost_alias',
        'proxy',
        'proxy_fcgi',
    ];

    private const string ADRESAR_MODULU = '/usr/lib/apache2/modules';

    /**
     * Copied the way the deploy uploads them (rsync `--copy-links`), so a link of the project is a file on the server.
     */
    private const array SOUBORY_PROJEKTU = [
        '.htaccess',
        'web/.htaccess',
        'admin/.htaccess',
        'cache/public/.htaccess',
        'maintenance.off.html',
        'admin/maintenance.off.html',
    ];

    private string $adresar;
    private int $port;
    /**
     * @var resource|null
     */
    private $proces;

    private function __construct()
    {
    }

    public static function spust(): self
    {
        $apache = new self();
        try {
            $apache->vytvorAdresare();
            $apache->napisKonfiguraci();
            $apache->nastartuj();
        } catch (\Throwable $throwable) {
            $apache->zastav();
            throw $throwable;
        }

        return $apache;
    }

    public function __destruct()
    {
        $this->zastav();
    }

    public function zastav(): void
    {
        if (is_resource($this->proces ?? null)) {
            proc_terminate($this->proces);
            proc_close($this->proces);
            $this->proces = null;
        }
        if (isset($this->adresar)) {
            (new Filesystem())->remove($this->adresar);
        }
    }

    public function getAdresarWebu(): string
    {
        return $this->adresar . '/srv/ostra';
    }

    public function zapniUdrzbuSouborem(): void
    {
        file_put_contents($this->getSouborUdrzby(), '');
    }

    /**
     * Like `ln -s maintenance.off.html maintenance.html` on the server.
     */
    public function zapniUdrzbuOdkazem(): void
    {
        symlink('maintenance.off.html', $this->getSouborUdrzby());
    }

    public function vypniUdrzbu(): void
    {
        $souborUdrzby = $this->getSouborUdrzby();
        if (is_link($souborUdrzby) || file_exists($souborUdrzby)) {
            unlink($souborUdrzby);
        }
    }

    /**
     * @return array{status: int, telo: string, hlavicky: array<string, list<string>>} hlavičky pod malými písmeny
     */
    public function pozadavek(string $host, string $cesta, string $metoda = 'GET'): array
    {
        $hlavicky = [];
        $curlHandle = curl_init("http://127.0.0.1:{$this->port}{$cesta}");
        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, ["Host: {$host}"]);
        curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curlHandle, CURLOPT_TIMEOUT, 30);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        match ($metoda) {
            'HEAD' => curl_setopt($curlHandle, CURLOPT_NOBODY, true),
            'POST' => curl_setopt_array($curlHandle, [
                CURLOPT_POST       => true,
                CURLOPT_POSTFIELDS => 'pole=hodnota',
            ]),
            default => curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, $metoda),
        };
        curl_setopt(
            $curlHandle,
            CURLOPT_HEADERFUNCTION,
            static function (\CurlHandle $handle, string $radekHlavicky) use (&$hlavicky): int {
                if (str_contains($radekHlavicky, ':')) {
                    [$nazev, $hodnota] = explode(':', $radekHlavicky, 2);
                    $hlavicky[strtolower(trim($nazev))][] = trim($hodnota);
                }

                return strlen($radekHlavicky);
            },
        );
        $telo = curl_exec($curlHandle);
        if ($telo === false) {
            throw new \RuntimeException(sprintf("Apache neodpověděl na %s %s%s: %s\n%s", $metoda, $host, $cesta, curl_error($curlHandle), $this->getChybovyLog()));
        }
        $status = (int) curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        curl_close($curlHandle);

        return [
            'status'   => $status,
            'telo'     => (string) $telo,
            'hlavicky' => $hlavicky,
        ];
    }

    private function getSouborUdrzby(): string
    {
        return $this->getAdresarWebu() . '/maintenance.html';
    }

    public function getChybovyLog(): string
    {
        $chybovyLog = $this->adresar . '/error.log';

        return is_file($chybovyLog)
            ? (string) file_get_contents($chybovyLog)
            : '';
    }

    private function vytvorAdresare(): void
    {
        $filesystem = new Filesystem();
        $this->adresar = sys_get_temp_dir() . '/gamecon-apache-jako-na-ostre-' . uniqid('', true);
        $web = $this->getAdresarWebu();
        foreach (['web', 'admin/deploy', 'cache/public', 'symfony/public', 'symfony/config'] as $podadresar) {
            $filesystem->mkdir("{$web}/{$podadresar}", 0775);
        }
        $filesystem->mkdir([$this->adresar . '/run', $this->adresar . '/obecna-stranka-serveru'], 0775);

        $projekt = dirname(__DIR__, 2);
        foreach (self::SOUBORY_PROJEKTU as $soubor) {
            $zdroj = "{$projekt}/{$soubor}";
            if (! is_file($zdroj)) {
                throw new \RuntimeException("Chybí soubor projektu {$soubor}");
            }
            $filesystem->copy($zdroj, "{$web}/{$soubor}");
        }

        // PHP se tu nespouští, takže index.php odpoví jako obyčejný text a stačí k tomu, že na něj Apache soubor přepsal
        file_put_contents($web . '/web/' . self::SKRIPT_BEZ_PHP_FPM, self::TELO_WEBU);
        file_put_contents($web . '/admin/' . self::SKRIPT_BEZ_PHP_FPM, self::TELO_ADMINU);
        file_put_contents($web . '/admin/deploy/' . self::SKRIPT_BEZ_PHP_FPM, self::TELO_NASAZENI);
        file_put_contents($web . '/web/index.php', self::TELO_WEBU);
        file_put_contents($web . '/admin/index.php', self::TELO_ADMINU);
        file_put_contents($web . '/admin/cron.php', self::TELO_CRONU);
        file_put_contents($web . '/admin/deploy/migrace.php', self::TELO_NASAZENI);
        file_put_contents($web . '/cache/public/styl.css', self::TELO_SOUBORU_Z_CACHE);
        file_put_contents($web . '/symfony/public/index.php', self::TELO_FRONT_CONTROLLERU_SYMFONY);
        file_put_contents($web . '/symfony/config/services.yaml', self::TELO_SOUBORU_VE_STROMU_SYMFONY);
        file_put_contents($web . '/symfony/config/bundles.php', self::TELO_SOUBORU_VE_STROMU_SYMFONY);
        file_put_contents($this->adresar . '/obecna-stranka-serveru/maintenance.html', self::TELO_OBECNE_STRANKY_SERVERU);

        $filesystem->symlink('ostra', $this->adresar . '/srv/' . self::HOST_WEBU);
        $filesystem->symlink(self::HOST_WEBU . '/admin', $this->adresar . '/srv/' . self::HOST_ADMINU);
    }

    private function napisKonfiguraci(): void
    {
        $this->port = $this->najdiVolnyPort();
        $moduly = '';
        foreach (self::MODULY as $modul) {
            $souborModulu = self::ADRESAR_MODULU . "/mod_{$modul}.so";
            if (! is_file($souborModulu)) {
                throw new \RuntimeException("Chybí modul Apache {$souborModulu}, testy běží v kontejneru web");
            }
            $moduly .= "LoadModule {$modul}_module {$souborModulu}\n";
        }
        $skriptBezPhpFpm = self::SKRIPT_BEZ_PHP_FPM;
        // pod rootem Apache bez User nenastartuje, jinak se User ignoruje
        $uzivatel = posix_geteuid() === 0
            ? "User www-data\nGroup www-data\n"
            : '';
        $konfigurace = <<<CONF
            ServerRoot {$this->adresar}
            ServerName localhost
            Listen 127.0.0.1:{$this->port}
            PidFile {$this->adresar}/run/apache.pid
            DefaultRuntimeDir {$this->adresar}/run
            Mutex file:{$this->adresar}/run default
            ErrorLog {$this->adresar}/error.log
            LogLevel warn
            {$uzivatel}{$moduly}TypesConfig /etc/mime.types

            <Directory />
                Options FollowSymLinks
                AllowOverride None
                Require all denied
            </Directory>

            <VirtualHost _default_:{$this->port}>
                UseCanonicalName         off
                VirtualDocumentRoot      {$this->adresar}/srv/%0
                DirectoryIndex           index.php index.html
                ErrorDocument            503 /maintenance.html
                Alias                    /maintenance.html {$this->adresar}/obecna-stranka-serveru/maintenance.html
                # ansible sets Retry-After here too; it is left out so that the tests prove the .htaccess files set it themselves
                <Directory {$this->adresar}/obecna-stranka-serveru>
                    Require              all granted
                </Directory>
                <Files {$skriptBezPhpFpm}>
                    SetHandler           "proxy:fcgi://127.0.0.1:1"
                </Files>
                <Directory {$this->adresar}/srv>
                    AllowOverride        all
                    Options              -Indexes -ExecCGI
                    Require              all granted
                </Directory>
            </VirtualHost>
            CONF;
        file_put_contents($this->adresar . '/httpd.conf', $konfigurace . "\n");
    }

    private function najdiVolnyPort(): int
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $kodChyby, $textChyby);
        if ($server === false) {
            throw new \RuntimeException("Nelze najít volný port: {$textChyby}");
        }
        $port = (int) substr((string) strrchr((string) stream_socket_get_name($server, false), ':'), 1);
        fclose($server);

        return $port;
    }

    private function nastartuj(): void
    {
        $apache = null;
        foreach (['/usr/sbin/apache2', '/usr/local/sbin/apache2'] as $kandidat) {
            if (is_executable($kandidat)) {
                $apache = $kandidat;
                break;
            }
        }
        if ($apache === null) {
            throw new \RuntimeException('Chybí apache2, testy běží v kontejneru web');
        }

        // -X drží Apache v jednom procesu v popředí, takže stačí proces ukončit
        $proces = proc_open(
            [$apache, '-f', $this->adresar . '/httpd.conf', '-X'],
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['file', $this->adresar . '/stdout.log', 'w'],
                2 => ['file', $this->adresar . '/stderr.log', 'w'],
            ],
            $pipes,
        );
        if ($proces === false) {
            throw new \RuntimeException('Apache se nepodařilo spustit');
        }
        $this->proces = $proces;

        for ($pokus = 0; $pokus < 100; ++$pokus) {
            if (! proc_get_status($proces)['running']) {
                throw new \RuntimeException("Apache skončil při startu:\n" . $this->getChybovyLog() . (string) file_get_contents($this->adresar . '/stderr.log'));
            }
            $spojeni = @fsockopen('127.0.0.1', $this->port, $kodChyby, $textChyby, 0.1);
            if ($spojeni !== false) {
                fclose($spojeni);

                return;
            }
            usleep(100_000);
        }

        throw new \RuntimeException("Apache do deseti sekund nezačal poslouchat na portu {$this->port}:\n" . $this->getChybovyLog());
    }
}
