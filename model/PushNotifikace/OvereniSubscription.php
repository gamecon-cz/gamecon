<?php

declare(strict_types=1);

namespace Gamecon\PushNotifikace;

/**
 * Kontrola subscription, kterou poslal prohlížeč. Endpoint je adresa, na kterou pak server sám posílá požadavky,
 * a píše ji uživatel: smí proto vést jen na push služby prohlížečů, které známe.
 */
final class OvereniSubscription
{
    /**
     * Push služby prohlížečů. Položka s tečkou na začátku je přípona (libovolná subdoména), jiná přesné jméno.
     * Seznam místo pouhého zákazu IP adres a interních jmen: jméno mimo seznam nikdy neuvidí DNS ani síť serveru,
     * takže přes něj nejde sahat do vnitřní sítě ani přepsáním DNS po kontrole. Cena: prohlížeč s jinou službou
     * dostane „nepodporováno“, dokud se sem jeho služba nedoplní; odmítnutý hostitel je v logu.
     */
    public const array PUSH_SLUZBY = [
        'fcm.googleapis.com',          // Chrome, Edge na Androidu, Opera, Samsung Internet, Brave
        'jmt17.google.com',            // Chrome, novější adresa téže služby
        '.push.services.mozilla.com',  // Firefox
        '.push.apple.com',             // Safari na macOS, web na ploše v iOS
        '.notify.windows.com',         // Edge na Windows
    ];

    /**
     * Délka sloupce, viz migrace. Skutečné endpointy mají stovky znaků.
     */
    public const int MAX_DELKA_ENDPOINTU = 2048;

    /**
     * Veřejný klíč prohlížeče: nekomprimovaný bod křivky P-256, začíná bajtem 0x04.
     */
    private const int DELKA_P256DH = 65;

    private const int DELKA_AUTH = 16;

    /**
     * @param mixed $subscription co poslal prohlížeč, tvar `PushSubscription.toJSON()`:
     *                            `{endpoint, keys: {p256dh, auth}}`
     *
     * @return array{endpoint: string, p256dh: string, auth: string}
     *
     * @throws ChybaPushNotifikace
     */
    public static function over(mixed $subscription): array
    {
        if (! is_array($subscription) || ! is_array($subscription['keys'] ?? null)) {
            throw new ChybaPushNotifikace('Prohlížeč neposlal údaje pro notifikace.');
        }
        $endpoint = $subscription['endpoint'] ?? null;
        $p256dh = $subscription['keys']['p256dh'] ?? null;
        $auth = $subscription['keys']['auth'] ?? null;
        if (! is_string($endpoint) || ! is_string($p256dh) || ! is_string($auth)) {
            throw new ChybaPushNotifikace('Prohlížeč neposlal údaje pro notifikace.');
        }
        self::overEndpoint($endpoint);

        $bajtyP256dh = Base64Url::dekoduj($p256dh);
        if ($bajtyP256dh === null || strlen($bajtyP256dh) !== self::DELKA_P256DH || $bajtyP256dh[0] !== "\x04"
            || Base64Url::getDelkaPoDekodovani($auth) !== self::DELKA_AUTH
        ) {
            throw new ChybaPushNotifikace('Prohlížeč poslal klíče pro notifikace v neznámém tvaru.');
        }

        return [
            'endpoint' => $endpoint,
            'p256dh'   => $p256dh,
            'auth'     => $auth,
        ];
    }

    /**
     * @throws ChybaPushNotifikace
     */
    public static function overEndpoint(string $endpoint): void
    {
        if (! self::isEndpointPovolen($endpoint)) {
            throw new ChybaPushNotifikace('Notifikace z tohoto prohlížeče zatím neumíme poslat, jeho službu pro notifikace neznáme.');
        }
    }

    public static function isEndpointPovolen(string $endpoint): bool
    {
        // jen viditelné ASCII: mezera, řídicí znak ani zpětné lomítko v adrese push služby nemají co dělat
        if (strlen($endpoint) > self::MAX_DELKA_ENDPOINTU || preg_match('/^[\x21-\x5B\x5D-\x7E]+$/D', $endpoint) !== 1) {
            return false;
        }
        $casti = parse_url($endpoint);
        if (! is_array($casti)
            || ($casti['scheme'] ?? '') !== 'https'
            || isset($casti['user'])
            || isset($casti['pass'])
            || isset($casti['fragment'])
            || (isset($casti['port']) && $casti['port'] !== 443)
        ) {
            return false;
        }

        return self::isHostitelPushSluzby($casti['host'] ?? '');
    }

    /**
     * Hostitel, na kterého endpoint vede, pro log: nic víc z endpointu se nikam nezapisuje.
     */
    public static function getHostitel(string $endpoint): string
    {
        $hostitel = parse_url($endpoint, PHP_URL_HOST);

        return is_string($hostitel)
            ? strtolower($hostitel)
            : '';
    }

    private static function isHostitelPushSluzby(string $hostitel): bool
    {
        $hostitel = strtolower($hostitel);
        // IP adresu (i v hranatých závorkách) seznam jmen stejně nepustí, tady jen pro jistotu výslovně
        if ($hostitel === '' || filter_var(trim($hostitel, '[]'), FILTER_VALIDATE_IP) !== false) {
            return false;
        }
        foreach (self::PUSH_SLUZBY as $sluzba) {
            if ($sluzba[0] === '.'
                ? str_ends_with($hostitel, $sluzba)
                : $hostitel === $sluzba
            ) {
                return true;
            }
        }

        return false;
    }
}
