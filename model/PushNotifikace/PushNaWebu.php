<?php

declare(strict_types=1);

namespace Gamecon\PushNotifikace;

/**
 * Co web kvůli push notifikacím vypisuje do stránek: odkaz na manifest a skript v hlavičce a ovládání v Nastavení.
 * Dokud jsou notifikace vypnuté, nevypíše nic, a web o nich navenek neví.
 */
final readonly class PushNaWebu
{
    private const string SKRIPT = 'soubory/push-notifikace.js';

    private const string IKONA_PRO_IOS = 'soubory/push/apple-touch-icon.png';

    /**
     * @param string $adresarWebu složka veřejného webu na disku (WWW), kvůli verzi skriptu v adrese
     */
    public function __construct(
        private PushNotifikace $pushNotifikace,
        private string $adresarWebu,
    ) {
    }

    /**
     * Manifest a ikona pro web na ploše každému návštěvníkovi: v iOS se web přidává na plochu ještě před
     * přihlášením. Skript a jeho nastavení jen přihlášenému.
     *
     * `crossorigin="use-credentials"`: manifest prohlížeč jinak stahuje bez cookies a brána před preview
     * (basic auth, cookie brány) by ho nevydala.
     */
    public function getHtmlHlavicky(?\Uzivatel $prihlaseny): string
    {
        if (! $this->pushNotifikace->isZapnuto()) {
            return '';
        }
        $urlWebu = rtrim($this->pushNotifikace->getUrlWebu(), '/');
        $html = sprintf(
            '<link rel="manifest" href="%s" crossorigin="use-credentials">'
            . '<link rel="apple-touch-icon" href="%s">',
            self::e($urlWebu . '/' . PushNotifikace::MANIFEST),
            self::e($urlWebu . '/' . self::IKONA_PRO_IOS),
        );
        if ($prihlaseny === null) {
            return $html;
        }

        return $html . sprintf(
            '<meta name="gc-push-notifikace" data-api="%s" data-service-worker="%s" data-scope="%s"'
            . ' data-vapid-klic="%s" data-uzivatel="%d">'
            . '<script defer src="%s"></script>',
            self::e($urlWebu . '/' . PushNotifikace::API),
            self::e($urlWebu . '/' . PushNotifikace::SERVICE_WORKER),
            self::e($urlWebu . '/'),
            self::e((string) $this->pushNotifikace->getVapidVerejnyKlic()),
            (int) $prihlaseny->id(),
            self::e($urlWebu . '/' . self::SKRIPT . '?version=' . md5_file($this->adresarWebu . '/' . self::SKRIPT)),
        );
    }

    /**
     * Ovládání notifikací na tomto zařízení pro stránku Nastavení. Stav a texty doplní skript; bez JavaScriptu
     * notifikace nefungují vůbec, a tak zůstane jen ta věta.
     */
    public function getHtmlNastaveni(): string
    {
        if (! $this->pushNotifikace->isZapnuto()) {
            return '';
        }

        return <<<'HTML'
            <h2 class="formular_sekceNadpis">Notifikace na tomto zařízení</h2>
            <div class="pushNotifikace" data-push-notifikace>
              <p data-push-stav aria-live="polite">Zjišťuji, jestli tu notifikace jdou zapnout. Je k tomu potřeba JavaScript.</p>
              <div class="pushNotifikace_napoveda" data-push-napoveda hidden></div>
              <p class="pushNotifikace_chyba" data-push-chyba role="alert" hidden></p>
              <button type="button" class="formular_sekundarni" style="margin-left: 0" data-push-tlacitko hidden></button>
            </div>
            HTML;
    }

    private static function e(string $hodnota): string
    {
        return htmlspecialchars($hodnota, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
