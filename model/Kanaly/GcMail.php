<?php

namespace Gamecon\Kanaly;

use Gamecon\Kanaly\Exceptions\ChybiEmailoveNastaveni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Třída pro sestavování mailu
 */
class GcMail
{
    public const FORMAT_HTML = 'html';
    public const FORMAT_TEXT = 'text';
    private const VYCHOZI_MAX_VELIKOST_LOGU_ODESLANYCH_MAILU_V_BAJTECH = 50 * 1024 * 1024; // 50 MB

    public static function vytvorZGlobals(string $text = ''): static
    {
        return new static(
            SystemoveNastaveni::zGlobals(),
            $text
        );
    }

    private array  $adresati      = [];
    private string $predmet       = '';
    private string $prilohaSoubor = '';
    private string $prilohaNazev  = '';

    public function __construct(
        private readonly SystemoveNastaveni $systemoveNastaveni,
        private string                      $text = '',
    )
    {
    }

    public function adresat(string $adresat): self
    {
        $this->adresati[] = $adresat;
        return $this;
    }

    public function adresati(array $adresati): self
    {
        $this->adresati = $adresati;
        return $this;
    }

    /**
     * Odešle sestavenou zprávu.
     * @return bool jestli se zprávu podařilo odeslat
     */
    public function odeslat(string $format = self::FORMAT_HTML)
    {
        $predmet = $this->pridejPrefixPodleProstredi($this->dejPredmet());
        $body    = $this->pridejPrefixPodleProstredi($this->dejText());
        $mail = (new Email())
            ->from($this->pridejPrefixPodleProstredi("GameCon <{$this->systemoveNastaveni->kontaktniEmailGc()}>"))
            ->subject($predmet);
        $mail->text(strip_tags($body));
        if ($format === self::FORMAT_HTML) {
            $mail->html($body);
        }

        $odeslano = false;

        $adresatiDoSouboru = $this->adresatiDoSouboru();
        if ($adresatiDoSouboru) {
            $mailDoSouboru = clone $mail;
            $mailDoSouboru->addBcc(...$adresatiDoSouboru);
            $cestaMailuDoSouboru = $this->cestaMailuDoSouboru();
            $ulozenoDoSouboru    = false;
            if ($cestaMailuDoSouboru) {
                $ulozenoDoSouboru = $this->zalogovatDo($cestaMailuDoSouboru, $mailDoSouboru->toString());
                $odeslano         = $ulozenoDoSouboru || $odeslano;
            }
            $this->zalogujAuditMailu(
                stav: $ulozenoDoSouboru ? 'ulozeno_do_souboru' : 'chyba_ulozeni_do_souboru',
                adresati: $adresatiDoSouboru,
                predmet: $predmet,
                format: $format,
            );
        }
        $adresatiPovoleniPodleRoli = $this->adresatiPovoleniPodleRoli();
        if ($adresatiPovoleniPodleRoli) {
            $mailKOdeslani = clone $mail;
            $mailKOdeslani->addBcc(...$adresatiPovoleniPodleRoli);
            if ($this->prilohaSoubor) {
                // do souboru přílohy dávat nebudeme
                $mailKOdeslani->attachFromPath($this->prilohaSoubor, $this->prilohaNazev);
            }
            $mailer = new Mailer($this->mailerTransport());
            try {
                $mailer->send($mailKOdeslani);
                $odeslano = true;
                $this->zalogujAuditMailu(
                    stav: 'odeslano',
                    adresati: $adresatiPovoleniPodleRoli,
                    predmet: $predmet,
                    format: $format,
                );
            } catch (Throwable $throwable) {
                $this->zalogujAuditMailu(
                    stav: 'chyba_odeslani',
                    adresati: $adresatiPovoleniPodleRoli,
                    predmet: $predmet,
                    format: $format,
                    chyba: $throwable->getMessage(),
                );
                throw $throwable;
            }
        }
        return $odeslano;
    }

    private function pridejPrefixPodleProstredi(string $text): string
    {
        $prefix = $this->systemoveNastaveni->prefixPodleProstredi();

        if ($prefix === '') {
            return $text;
        }
        if (preg_match('~^\s*<html>\s*<body>~i', $text)) {
            return preg_replace('~^\s*<html>\s*<body>~i', '$0' . $prefix . ' ', $text);
        }
        return $prefix . ' ' . $text;
    }

    private function mailerTransport(): Transport\TransportInterface
    {
        if (!defined('MAILER_DSN')) {
            /**
             * Návod @link https://symfony.com/doc/current/mailer.html#transport-setup
             * SMTP server @link https://client.wedos.com/webhosting/webhost-detail.html?id=16779 'Adresy služeb' dole
             * Pro Wedos SMTP použij port 587 (TLS), protože SSL z PHP z Wedos serveru nefunguje.
             */
            throw new ChybiEmailoveNastaveni(
                "Pro odeslání emailu je třeba nastavit konstantu 'MAILER_DSN'"
            );
        }
        return Transport::fromDsn(MAILER_DSN);
    }

    private function adresatiDoSouboru(): array
    {
        if (!defined('MAILY_DO_SOUBORU') || !MAILY_DO_SOUBORU) {
            return [];
        }
        return array_diff($this->adresati, $this->adresatiPovoleniPodleRoli());
    }

    private function adresatiPovoleniPodleRoli(): array
    {
        if (!defined('MAILY_DO_SOUBORU') || !MAILY_DO_SOUBORU) {
            return $this->adresati;
        }
        if (!defined('MAILY_ROLIM') || !MAILY_ROLIM) {
            return [];
        }
        $povoleniPodleRoli = [];
        foreach ($this->adresati as $adresat) {
            if (!preg_match('~(?<email>[^@\s<>]+@[^@\s<>]+)~', $adresat, $matches)) {
                continue;
            }
            $email    = $matches['email'];
            $uzivatel = \Uzivatel::zEmailu($email);
            if (!$uzivatel) {
                continue;
            }
            foreach ((array)MAILY_ROLIM as $role) {
                if ($uzivatel->maRoli($role)) {
                    $povoleniPodleRoli[] = $adresat;
                    break;
                }
            }
        }
        return $povoleniPodleRoli;
    }

    private function cestaMailuDoSouboru(): ?string
    {
        if (!defined('MAILY_DO_SOUBORU') || !MAILY_DO_SOUBORU) {
            return null;
        }
        if (is_string(MAILY_DO_SOUBORU)) {
            return MAILY_DO_SOUBORU;
        }
        if (defined('SPEC') && is_string(SPEC) && SPEC !== '') {
            return rtrim(SPEC, '/\\') . '/maily.log';
        }
        if (defined('LOGY') && is_string(LOGY) && LOGY !== '') {
            return rtrim(LOGY, '/\\') . '/maily.log';
        }

        return null;
    }

    private function cestaLoguOdeslanychMailu(): ?string
    {
        if (defined('MAILY_ODESLANE_DO_SOUBORU')) {
            if (!MAILY_ODESLANE_DO_SOUBORU) {
                return null;
            }
            if (is_string(MAILY_ODESLANE_DO_SOUBORU)) {
                return MAILY_ODESLANE_DO_SOUBORU;
            }
        }
        if (defined('LOGY') && is_string(LOGY) && LOGY !== '') {
            return rtrim(LOGY, '/\\') . '/maily-odeslane.log';
        }
        if (defined('SPEC') && is_string(SPEC) && SPEC !== '') {
            return rtrim(SPEC, '/\\') . '/maily-odeslane.log';
        }

        return null;
    }

    public function predmet(string $predmet): self
    {
        $this->predmet = $predmet;
        return $this;
    }

    public function text(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    protected function zalogovatDo(string $soubor, string $obsah): bool
    {
        $adresar = dirname($soubor);
        if (
            $adresar
            && !is_dir($adresar)
            && $this->provedBezPhpWarningu(
                static fn() => mkdir($adresar, 0770, true),
                "Nepodařilo se vytvořit adresář '$adresar' pro logování e-mailů.",
            ) === false
            && !is_dir($adresar)
        ) {
            return false;
        }

        $zapsano = $this->provedBezPhpWarningu(
            static fn() => file_put_contents($soubor, $obsah, FILE_APPEND | LOCK_EX),
            "Nepodařilo se zapsat audit e-mailu do '$soubor'.",
        );
        if ($zapsano === false) {
            return false;
        }

        return true;
    }

    private function zalogujChybuPriZapisuLogu(string $zprava): void
    {
        error_log('[GcMail] ' . $zprava);
    }

    private function maxVelikostLoguOdeslanychMailuVBajtech(): int
    {
        if (!defined('MAILY_ODESLANE_MAX_VELIKOST_BAJTU')) {
            return self::VYCHOZI_MAX_VELIKOST_LOGU_ODESLANYCH_MAILU_V_BAJTECH;
        }
        if (MAILY_ODESLANE_MAX_VELIKOST_BAJTU === false) {
            return 0;
        }
        if (is_numeric(MAILY_ODESLANE_MAX_VELIKOST_BAJTU)) {
            return max(0, (int)MAILY_ODESLANE_MAX_VELIKOST_BAJTU);
        }

        return self::VYCHOZI_MAX_VELIKOST_LOGU_ODESLANYCH_MAILU_V_BAJTECH;
    }

    private function zalogujAuditDoSouboru(string $soubor, string $obsah): bool
    {
        $souborSeZamkem = $soubor . '.lock';
        $adresarZamku   = dirname($souborSeZamkem);
        if (
            $adresarZamku
            && !is_dir($adresarZamku)
            && $this->provedBezPhpWarningu(
                static fn() => mkdir($adresarZamku, 0770, true),
                "Nepodařilo se vytvořit adresář '$adresarZamku' pro zámek audit logu e-mailů.",
            ) === false
            && !is_dir($adresarZamku)
        ) {
            return false;
        }

        $souborZamku = $this->provedBezPhpWarningu(
            static fn() => fopen($souborSeZamkem, 'c'),
            "Nepodařilo se otevřít zámek '$souborSeZamkem' pro audit e-mailů.",
        );
        if ($souborZamku === false) {
            return false;
        }

        if (!flock($souborZamku, LOCK_EX)) {
            fclose($souborZamku);
            $this->zalogujChybuPriZapisuLogu("Nepodařilo se získat zámek '$souborSeZamkem' pro audit e-mailů.");
            return false;
        }

        try {
            if (!$this->rotujAuditLogPokudJeMocVelky($soubor, strlen($obsah))) {
                return false;
            }

            return $this->zalogovatDo($soubor, $obsah);
        } finally {
            flock($souborZamku, LOCK_UN);
            fclose($souborZamku);
        }
    }

    private function rotujAuditLogPokudJeMocVelky(string $soubor, int $prirustekVBajtech): bool
    {
        $maxVelikostVBajtech = $this->maxVelikostLoguOdeslanychMailuVBajtech();
        if ($maxVelikostVBajtech <= 0 || !is_file($soubor)) {
            return true;
        }

        clearstatcache(true, $soubor);
        $aktualniVelikostVBajtech = $this->provedBezPhpWarningu(
            static fn() => filesize($soubor),
            "Nepodařilo se zjistit velikost audit logu '$soubor'.",
        );
        if ($aktualniVelikostVBajtech === false) {
            return false;
        }
        if ($aktualniVelikostVBajtech + $prirustekVBajtech <= $maxVelikostVBajtech) {
            return true;
        }

        $rotovanySoubor = $this->cestaRotovanehoAuditLogu($soubor);
        $zrotovano = $this->provedBezPhpWarningu(
            static fn() => rename($soubor, $rotovanySoubor),
            "Nepodařilo se rotovat audit log '$soubor' na '$rotovanySoubor'.",
        );
        if ($zrotovano === false) {
            return false;
        }

        $this->smazStareRotovaneLogy($soubor);

        return true;
    }

    private function cestaRotovanehoAuditLogu(string $soubor): string
    {
        $cestaInfo = pathinfo($soubor);
        $adresar   = $cestaInfo['dirname'] ?? '.';
        $jmeno     = $cestaInfo['filename'] ?? basename($soubor);
        $pripona   = isset($cestaInfo['extension']) && $cestaInfo['extension'] !== ''
            ? '.' . $cestaInfo['extension']
            : '';
        $casovaZnacka = date('Ymd-His');

        $rotovanySoubor = $adresar . '/' . $jmeno . '-' . $casovaZnacka . $pripona;
        $poradi         = 1;
        while (file_exists($rotovanySoubor)) {
            $rotovanySoubor = $adresar . '/' . $jmeno . '-' . $casovaZnacka . '-' . $poradi . $pripona;
            $poradi++;
        }

        return $rotovanySoubor;
    }

    private function smazStareRotovaneLogy(string $soubor): void
    {
        $cestaInfo = pathinfo($soubor);
        $adresar   = $cestaInfo['dirname'] ?? '.';
        $jmeno     = $cestaInfo['filename'] ?? basename($soubor);
        $pripona   = isset($cestaInfo['extension']) && $cestaInfo['extension'] !== ''
            ? '.' . $cestaInfo['extension']
            : '';

        $pattern = $adresar . '/' . $jmeno . '-*' . $pripona;
        $soubory = glob($pattern);
        if (!$soubory) {
            return;
        }

        $maxStariVSekundach = 2 * 365.25 * 24 * 3600; // 2 roky
        $ted                = time();

        foreach ($soubory as $rotovanySoubor) {
            $casModifikace = @filemtime($rotovanySoubor);
            if ($casModifikace === false) {
                continue;
            }
            if (($ted - $casModifikace) > $maxStariVSekundach) {
                $this->provedBezPhpWarningu(
                    static fn() => unlink($rotovanySoubor),
                    "Nepodařilo se smazat starý rotovaný log '$rotovanySoubor'.",
                );
            }
        }
    }

    private function provedBezPhpWarningu(callable $operace, string $zpravaPriSelhani): mixed
    {
        $chyba = null;
        set_error_handler(
            static function (int $severity, string $message) use (&$chyba): bool {
                $chyba = $message;
                return true;
            },
        );
        try {
            $vysledek = $operace();
        } finally {
            restore_error_handler();
        }

        if ($vysledek === false) {
            $this->zalogujChybuPriZapisuLogu(
                $zpravaPriSelhani . ($chyba ? " Detail: $chyba" : '')
            );
        }

        return $vysledek;
    }

    private function zalogujAuditMailu(
        string $stav,
        array  $adresati,
        string $predmet,
        string $format,
        string $chyba = '',
    ): void {
        if (!$adresati) {
            return;
        }
        $cestaLogu = $this->cestaLoguOdeslanychMailu();
        if (!$cestaLogu) {
            return;
        }

        $zaznam = [
            'kdy'      => date(DATE_ATOM),
            'stav'     => $stav,
            'format'   => $format,
            'predmet'  => $predmet,
            'adresati' => array_values($adresati),
        ];
        if ($this->prilohaSoubor !== '') {
            $zaznam['prilohaSoubor'] = $this->prilohaSoubor;
        }
        if ($this->prilohaNazev !== '') {
            $zaznam['prilohaNazev'] = $this->prilohaNazev;
        }
        if ($chyba !== '') {
            $zaznam['chyba'] = $chyba;
        }

        $zaznamJson = json_encode(
            $zaznam,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if (!is_string($zaznamJson)) {
            return;
        }
        $this->zalogujAuditDoSouboru(
            $cestaLogu,
            $zaznamJson . PHP_EOL
        );
    }

    public function dejText(): string
    {
        return $this->text;
    }

    public function dejPredmet(): string
    {
        return $this->predmet;
    }

    public function prilohaSoubor(string $cesta): self
    {
        $this->prilohaSoubor = $cesta;
        return $this;
    }

    public function prilohaNazev(string $nazev): self
    {
        $this->prilohaNazev = $nazev;
        return $this;
    }

}
