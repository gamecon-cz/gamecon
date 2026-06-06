<?php

namespace Gamecon\Kanaly;

use Gamecon\Kanaly\Exceptions\ChybiEmailoveNastaveni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Třída pro sestavování mailu
 */
class GcMail
{
    public const FORMAT_HTML = 'html';
    public const FORMAT_TEXT = 'text';
    private const VYCHOZI_EMAIL_ODESILATELE = 'gamecon.fallback@seznam.cz';

    public static function vytvorZGlobals(string $text = ''): static
    {
        return new static(
            SystemoveNastaveni::zGlobals(),
            $text,
            MailLogger::zGlobals(),
        );
    }

    private array  $adresati = [];
    private string $predmet  = '';
    private ?Address $odesilatel = null;
    /** @var array<int, array{soubor: string, nazev: string}> */
    private array  $prilohy  = [];

    public function __construct(
        private readonly SystemoveNastaveni $systemoveNastaveni,
        private string                      $text = '',
        private readonly ?MailLogger        $mailLogger = null,
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

    public function odesilatel(Address $odesilatel): self
    {
        $this->odesilatel = $odesilatel;
        return $this;
    }

    /**
     * Odešle sestavenou zprávu.
     * @return bool jestli se zprávu podařilo odeslat
     */
    public function odeslat(string $format = self::FORMAT_HTML)
    {
        $predmet = $this->pridejPrefixPodleProstredi($this->dejPredmet());
        $mail    = (new Email())
            ->from($this->odesilatelSPrefixemProstredi())
            ->subject($predmet);
        $body = $this->pridejPrefixPodleProstredi($this->dejText());
        $mail->text(strip_tags($body));
        $teloHtml = null;
        if ($format === self::FORMAT_HTML) {
            $mail->html($body);
            $teloHtml = $body;
        }

        $odeslano = false;

        $adresatiDoSouboru = $this->adresatiDoSouboru();
        if ($adresatiDoSouboru) {
            $mail->addBcc(...$adresatiDoSouboru);
            $odeslano = $this->zalogovatDo(MAILY_DO_SOUBORU, $mail->toString()) || $odeslano;
            $this->zalogujOdeslani($predmet, $format, $adresatiDoSouboru, $mail->toString(), $teloHtml);
        }
        $adresati = $this->adresatiPovoleniPodleRoli();
        if ($adresati) {
            $mail->addBcc(...$adresati);
            foreach ($this->prilohy as $priloha) {
                if ($priloha['soubor'] === '') {
                    continue;
                }
                // do souboru přílohy dávat nebudeme
                $mail->attachFromPath($priloha['soubor'], $priloha['nazev']);
            }
            $mailer = new Mailer($this->mailerTransport());
            try {
                $mailer->send($mail);
                $odeslano = true;
                $this->zalogujOdeslani($predmet, $format, $adresati, $mail->toString(), $teloHtml);
            } catch (Throwable $chyba) {
                $this->zalogujOdeslani($predmet, $format, $adresati, $mail->toString(), $teloHtml, $chyba->getMessage());
                throw $chyba;
            }
        }
        return $odeslano;
    }

    private function zalogujOdeslani(
        string  $predmet,
        string  $format,
        array   $adresati,
        string  $telo,
        ?string $teloHtml = null,
        ?string $chyba = null,
    ): void {
        $mailLogger = $this->mailLogger ?? MailLogger::zGlobals();
        $pocetPriloh = 0;
        foreach ($this->prilohy as $priloha) {
            if ($priloha['soubor'] !== '') {
                $pocetPriloh++;
            }
        }
        $mailLogger->zalogujOdeslani(
            predmet: $predmet,
            format: $format,
            adresati: $adresati,
            pocetPriloh: $pocetPriloh,
            telo: $telo,
            teloHtml: $teloHtml,
            chyba: $chyba,
        );
    }

    private function vychoziOdesilatel(): Address
    {
        return new Address(self::VYCHOZI_EMAIL_ODESILATELE, 'GameCon');
    }

    private function odesilatelSPrefixemProstredi(): Address
    {
        $odesilatel = $this->odesilatel ?? $this->vychoziOdesilatel();
        $prefix     = $this->systemoveNastaveni->prefixPodleProstredi();
        if ($prefix === '') {
            return $odesilatel;
        }
        $jmeno = $odesilatel->getName();

        return new Address(
            $odesilatel->getAddress(),
            $jmeno === ''
                ? $prefix
                : $prefix . ' ' . $jmeno,
        );
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
             * SMTP server: smtp.gmail.com:465
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

    private function zalogovatDo(string $soubor, string $obsah)
    {
        return file_put_contents($soubor, $obsah, FILE_APPEND);
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
        $this->prilohy[] = [
            'soubor' => $cesta,
            'nazev'  => basename($cesta),
        ];
        return $this;
    }

    public function prilohaNazev(string $nazev): self
    {
        if ($this->prilohy === []) {
            $this->prilohy[] = [
                'soubor' => '',
                'nazev'  => $nazev,
            ];
            return $this;
        }

        $posledniPriloha = array_key_last($this->prilohy);
        if ($posledniPriloha !== null) {
            $this->prilohy[$posledniPriloha]['nazev'] = $nazev;
        }
        return $this;
    }

}
