<?php

declare(strict_types=1);

namespace Gamecon\PushNotifikace;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;

/**
 * Vstupní bod web push notifikací: jestli jsou zapnuté a služby poskládané z nastavení prostředí.
 * Koncept, pravidla a gotchas: docs/generated/web-push-notifikace.md, nasazení: docs/push-notifikace.md.
 */
final class PushNotifikace
{
    /**
     * Cesta service workeru a manifestu pod adresou webu, viz web/sw.js a web/manifest.webmanifest.
     */
    public const string SERVICE_WORKER = 'sw.js';

    public const string MANIFEST = 'manifest.webmanifest';

    /**
     * API webu, které volá skript stránky i service worker.
     */
    public const string API = 'api/pushSubscription';

    public function __construct(
        private readonly bool $zapnutoVNastaveni,
        private readonly ?VapidKlice $vapidKlice,
        private readonly string $urlWebu,
        private readonly string $adresarLogu,
        private readonly ClockInterface $hodiny = new NativeClock(),
        private ?ClientInterface $httpKlient = null,
    ) {
    }

    public static function zNastaveni(SystemoveNastaveni $systemoveNastaveni): self
    {
        return new self(
            zapnutoVNastaveni: $systemoveNastaveni->isPushNotifikaceZapnute(),
            vapidKlice: VapidKlice::zKonstant(),
            urlWebu: URL_WEBU,
            adresarLogu: LOGY,
        );
    }

    /**
     * Vidí uživatelé ovládání a smí se odesílat? Jen když je zapnutý přepínač v Nastavení a prostředí má VAPID klíče.
     */
    public function isZapnuto(): bool
    {
        return $this->zapnutoVNastaveni && $this->vapidKlice !== null;
    }

    public function isZapnutoVNastaveni(): bool
    {
        return $this->zapnutoVNastaveni;
    }

    /**
     * Má prostředí platné VAPID klíče?
     */
    public function isNakonfigurovano(): bool
    {
        return $this->vapidKlice !== null;
    }

    /**
     * Veřejný klíč, se kterým si prohlížeč zakládá subscription.
     */
    public function getVapidVerejnyKlic(): ?string
    {
        return $this->vapidKlice?->verejny;
    }

    public function getUrlWebu(): string
    {
        return $this->urlWebu;
    }

    /**
     * @throws ChybaPushNotifikace když prostředí nemá VAPID klíče
     */
    public function getSubscriptions(): PushSubscriptions
    {
        return new PushSubscriptions($this->getVapidKlice()->verejny, $this->urlWebu);
    }

    public function getZaznamOdeslani(): ZaznamOdeslani
    {
        return new ZaznamOdeslani($this->hodiny, $this->adresarLogu);
    }

    /**
     * @throws ChybaPushNotifikace když prostředí nemá VAPID klíče
     */
    public function getSpravaZarizeni(): SpravaZarizeni
    {
        return new SpravaZarizeni($this->getSubscriptions(), $this->getZaznamOdeslani());
    }

    /**
     * Odesílač pro cron, frontu a každého dalšího, kdo posílá sám za sebe. Z adminu se posílá přes
     * {@see self::getOdeslaniZAdminu()}, které hlídá právo a limit.
     *
     * @throws ChybaPushNotifikace když jsou notifikace vypnuté nebo prostředí nemá VAPID klíče
     */
    public function getOdesilac(): PushOdesilac
    {
        if (! $this->zapnutoVNastaveni) {
            // vypínač platí pro každého, kdo posílá, ne jen pro formulář v adminu
            throw new ChybaPushNotifikace('Push notifikace jsou vypnuté. Zapnout je jde v Nastavení, skupina Notifikace.', 409);
        }

        return new PushOdesilac(
            $this->getVapidKlice(),
            $this->getSubscriptions(),
            $this->getZaznamOdeslani(),
            $this->httpKlient ??= self::vytvorHttpKlienta(),
            $this->urlWebu,
        );
    }

    public function getOdeslaniZAdminu(): OdeslaniZAdminu
    {
        return new OdeslaniZAdminu($this);
    }

    /**
     * Klient pro požadavky na push služby. Adresu, na kterou jde, napsal uživatel (endpoint), proto:
     * nenásleduje přesměrování (služba ze seznamu by jinak mohla poslat server kamkoli), má krátký časový limit
     * (odesílá se v požadavku admina) a chybový stav vrací jako odpověď.
     */
    public static function vytvorHttpKlienta(): ClientInterface
    {
        return new Client([
            RequestOptions::ALLOW_REDIRECTS => false,
            RequestOptions::HTTP_ERRORS     => false,
            RequestOptions::CONNECT_TIMEOUT => 5,
            RequestOptions::TIMEOUT         => 10,
            // jen https, ať adresa vypadá jakkoli
            'curl' => [
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            ],
        ]);
    }

    /**
     * @throws ChybaPushNotifikace
     */
    private function getVapidKlice(): VapidKlice
    {
        return $this->vapidKlice
            ?? throw new ChybaPushNotifikace('Push notifikace nejsou na tomto prostředí nastavené, chybí VAPID klíče.', 503);
    }
}
