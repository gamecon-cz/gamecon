<?php

declare(strict_types=1);

namespace Gamecon\Tests\PushNotifikace;

use Gamecon\PushNotifikace\PushSubscriptions;
use Gamecon\PushNotifikace\VapidKlice;
use Minishlink\WebPush\VAPID;

/**
 * Uživatelé, trvalá přihlášení a subscriptions pro testy push notifikací. Subscription má skutečný klíč křivky
 * P-256, aby pro ni knihovna uměla zprávu zašifrovat; push službu v testech hraje {@see FalesnyHttpKlient}.
 */
trait PushNotifikaceTestTrait
{
    protected const string URL_WEBU_TESTU = 'https://gamecon.test';

    private static ?VapidKlice $vapidKliceTestu = null;

    protected static function vapidKlice(): VapidKlice
    {
        if (self::$vapidKliceTestu === null) {
            $klice = VAPID::createVapidKeys();
            self::$vapidKliceTestu = VapidKlice::zHodnot($klice['publicKey'], $klice['privateKey'], self::URL_WEBU_TESTU)
                ?? throw new \LogicException('Knihovna vygenerovala VAPID klíče, které neprošly kontrolou.');
        }

        return self::$vapidKliceTestu;
    }

    protected static function subscriptions(): PushSubscriptions
    {
        return new PushSubscriptions(self::vapidKlice()->verejny, self::URL_WEBU_TESTU);
    }

    protected function vytvorUzivatelePush(): int
    {
        dbQuery(<<<'SQL'
            INSERT INTO uzivatele_hodnoty SET
                login_uzivatele = $0,
                email1_uzivatele = $1,
                jmeno_uzivatele = 'Test',
                prijmeni_uzivatele = 'PushNotifikace',
                random = ''
            SQL, [
    0         => 'test_push_' . uniqid('', true),
            1 => 'test.push.' . uniqid('', true) . '@example.org',
]);

        return (int) dbInsertId();
    }

    /**
     * @return array{id: int, cookie: string} id řádku a cookie, kterou by nesl prohlížeč
     */
    protected function zalozTrvalePrihlaseniPush(int $idUzivatele): array
    {
        $selektor = bin2hex(random_bytes(32));
        $token = bin2hex(random_bytes(32));
        dbQuery(<<<'SQL'
            INSERT INTO uzivatele_trvala_prihlaseni SET id_uzivatele = $0, selektor_hash = $1, token_hash = $2
            SQL, [
    0         => $idUzivatele,
            1 => hash('sha256', $selektor),
            2 => hash('sha256', $token),
]);

        return [
            'id'     => (int) dbInsertId(),
            'cookie' => $selektor . '.0.' . $token,
        ];
    }

    /**
     * To, co prohlížeč pošle z `PushSubscription.toJSON()`.
     *
     * @return array{endpoint: string, keys: array{p256dh: string, auth: string}}
     */
    protected static function subscriptionProhlizece(string $endpoint = ''): array
    {
        $klic = openssl_pkey_get_details(openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]))['ec'];
        $bod = "\x04" . str_pad($klic['x'], 32, "\0", STR_PAD_LEFT) . str_pad($klic['y'], 32, "\0", STR_PAD_LEFT);

        return [
            'endpoint' => $endpoint !== ''
                ? $endpoint
                : 'https://fcm.googleapis.com/fcm/send/' . bin2hex(random_bytes(16)),
            'keys' => [
                'p256dh' => self::base64Url($bod),
                'auth'   => self::base64Url(random_bytes(16)),
            ],
        ];
    }

    /**
     * Zapne notifikace na novém zařízení uživatele a vrátí endpoint.
     */
    protected function zapniNaNovemZarizeni(int $idUzivatele, ?PushSubscriptions $subscriptions = null, string $endpoint = ''): string
    {
        $subscription = self::subscriptionProhlizece($endpoint);
        ($subscriptions ?? self::subscriptions())->uloz(
            $idUzivatele,
            $this->zalozTrvalePrihlaseniPush($idUzivatele)['id'],
            [
                'endpoint' => $subscription['endpoint'],
                'p256dh'   => $subscription['keys']['p256dh'],
                'auth'     => $subscription['keys']['auth'],
            ],
            'Chrome, Android',
        );

        return $subscription['endpoint'];
    }

    protected function getPocetSubscriptions(int $idUzivatele): int
    {
        return (int) dbOneCol(<<<'SQL'
            SELECT COUNT(*) FROM uzivatele_push_subscriptions WHERE id_uzivatele = $0
            SQL, [
            0 => $idUzivatele,
        ]);
    }

    protected static function base64Url(string $bajty): string
    {
        return rtrim(strtr(base64_encode($bajty), '+/', '-_'), '=');
    }
}
