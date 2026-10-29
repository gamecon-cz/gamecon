<?php

declare(strict_types=1);

namespace Gamecon\Web\Motiv;

use Gamecon\Pravo;
use Gamecon\Web\VzhledWebu;
use Gamecon\Web\VzhledWebuEnum;

/**
 * A preview of the draft colours or of a special theme on the real web, before visitors see it. Only for those who
 * may change the look (the web panel of the admin), anyone else gets the web as it is. Remembered in a cookie that
 * ends with the browser, so the preview follows the designer from page to page.
 */
class NahledMotivuWebu
{
    /**
     * ?nahledMotivu=navrh-barev / ?nahledMotivu=<theme> starts the preview, ?nahledMotivu=konec ends it
     */
    public const string GET_KLIC = 'nahledMotivu';
    public const string COOKIE = 'nahledMotivu';
    public const string NAVRH_BAREV = 'navrh-barev';
    public const string KONEC = 'konec';

    /**
     * @param array<string, mixed> $cookies typically $_COOKIE
     */
    public function __construct(
        private readonly array $cookies,
    ) {
    }

    public static function smiNahlizet(?\Uzivatel $uzivatel): bool
    {
        return $uzivatel?->maPravo(Pravo::ADMINISTRACE_WEB) ?? false;
    }

    public function jeNavrhBarev(): bool
    {
        return $this->getVolba() === self::NAVRH_BAREV;
    }

    public function getMotiv(): ?MotivWebuEnum
    {
        return MotivWebuEnum::tryFrom($this->getVolba());
    }

    /**
     * Starts or ends the preview chosen by ?nahledMotivu=…, an unknown value changes nothing.
     *
     * @return bool false when the value is unknown
     */
    public function zpracujVolbu(string $volba): bool
    {
        if ($volba === self::KONEC) {
            setcookie(self::COOKIE, '', [
                'expires'  => 1,
                'path'     => '/',
                'samesite' => 'Lax',
            ]);

            return true;
        }
        if ($volba !== self::NAVRH_BAREV && MotivWebuEnum::tryFrom($volba) === null) {
            return false;
        }
        // expires 0: the preview ends with the browser, nobody gets stuck in it
        setcookie(self::COOKIE, $volba, [
            'expires'  => 0,
            'path'     => '/',
            'samesite' => 'Lax',
        ]);

        return true;
    }

    /**
     * The URL of the web that starts the preview, it switches to the modern look as well, as only that one has themes.
     *
     * @param string $volba NAVRH_BAREV or the value of a theme
     */
    public static function getUrlNahledu(string $urlWebu, string $volba): string
    {
        return $urlWebu . '/?' . http_build_query([
            VzhledWebu::GET_KLIC => VzhledWebuEnum::MODERNI->value,
            self::GET_KLIC       => $volba,
        ]);
    }

    /**
     * The URL to end the preview from the current page.
     *
     * @param array<string, mixed> $query typically $_GET, without the 'req' added by the rewrite
     */
    public static function getUrlKonce(string $cesta, array $query): string
    {
        $query[self::GET_KLIC] = self::KONEC;

        return $cesta . '?' . http_build_query($query);
    }

    private function getVolba(): string
    {
        return (string) ($this->cookies[self::COOKIE] ?? '');
    }
}
