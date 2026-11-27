<?php

declare(strict_types=1);

namespace Gamecon\Tests\PushNotifikace;

use Gamecon\PushNotifikace\ChybaPushNotifikace;
use Gamecon\PushNotifikace\OvereniSubscription;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Endpoint píše uživatel a server na něj pak sám posílá požadavky: smí vést jen na push služby prohlížečů.
 */
class OvereniSubscriptionTest extends TestCase
{
    use PushNotifikaceTestTrait;

    #[DataProvider('povoleneEndpointy')]
    public function testEndpointPushSluzbyProjde(string $endpoint): void
    {
        self::assertTrue(OvereniSubscription::isEndpointPovolen($endpoint));
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function povoleneEndpointy(): \Generator
    {
        yield 'Chrome (FCM)' => ['https://fcm.googleapis.com/fcm/send/dQw4w9WgXcQ:APA91bH'];
        yield 'Chrome, novější adresa' => ['https://jmt17.google.com/fcm/send/dQw4w9WgXcQ'];
        yield 'Firefox' => ['https://updates.push.services.mozilla.com/wpush/v2/gAAAAAB'];
        yield 'Safari a iOS' => ['https://web.push.apple.com/QGuNxK'];
        yield 'Edge' => ['https://wns2-par02p.notify.windows.com/w/?token=BQYAAAD'];
        yield 'výslovný port 443' => ['https://fcm.googleapis.com:443/fcm/send/abc'];
        yield 'velká písmena v hostiteli' => ['https://FCM.googleapis.com/fcm/send/abc'];
    }

    #[DataProvider('odmitnuteEndpointy')]
    public function testJinyEndpointNeprojde(string $endpoint): void
    {
        self::assertFalse(OvereniSubscription::isEndpointPovolen($endpoint));
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function odmitnuteEndpointy(): \Generator
    {
        yield 'http' => ['http://fcm.googleapis.com/fcm/send/abc'];
        yield 'neznámý hostitel' => ['https://example.com/push/abc'];
        yield 'IP adresa' => ['https://127.0.0.1/push'];
        yield 'IP adresa vnitřní sítě' => ['https://10.0.0.5/push'];
        yield 'metadata cloudu' => ['https://169.254.169.254/latest/meta-data'];
        yield 'IPv6' => ['https://[::1]/push'];
        yield 'localhost' => ['https://localhost/push'];
        yield 'jiný port' => ['https://fcm.googleapis.com:8443/fcm/send/abc'];
        yield 'jméno a heslo v adrese' => ['https://fcm.googleapis.com@example.com/push'];
        yield 'jméno před hostitelem push služby' => ['https://uzivatel:heslo@fcm.googleapis.com/fcm/send/abc'];
        yield 'push služba jen jako začátek jména' => ['https://fcm.googleapis.com.example.com/push'];
        yield 'push služba jen jako konec jména bez tečky' => ['https://zlypush.apple.com.example.org/x'];
        yield 'přípona bez tečky před ní' => ['https://zlenotify.windows.com/w'];
        yield 'kotva' => ['https://fcm.googleapis.com/fcm/send/abc#kotva'];
        yield 'mezera' => ['https://fcm.googleapis.com/fcm/send/a b'];
        yield 'konec řádku' => ["https://fcm.googleapis.com/fcm/send/abc\n"];
        yield 'zpětné lomítko' => ['https://fcm.googleapis.com\\@example.com/x'];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'soubor' => ['file:///etc/passwd'];
        yield 'prázdný' => [''];
        yield 'moc dlouhý' => ['https://fcm.googleapis.com/fcm/send/' . str_repeat('a', OvereniSubscription::MAX_DELKA_ENDPOINTU)];
    }

    public function testSubscriptionProhlizeceProjde(): void
    {
        $subscription = self::subscriptionProhlizece();

        self::assertSame(
            [
                'endpoint' => $subscription['endpoint'],
                'p256dh'   => $subscription['keys']['p256dh'],
                'auth'     => $subscription['keys']['auth'],
            ],
            OvereniSubscription::over($subscription),
        );
    }

    #[DataProvider('neplatneSubscriptions')]
    public function testNeplatnaSubscriptionNeprojde(callable $pokaz): void
    {
        $this->expectException(ChybaPushNotifikace::class);

        OvereniSubscription::over($pokaz(self::subscriptionProhlizece()));
    }

    /**
     * @return \Generator<string, array{callable(array<string, mixed>): mixed}>
     */
    public static function neplatneSubscriptions(): \Generator
    {
        yield 'není pole' => [
            static fn (array $s): string => 'subscription',
        ];
        yield 'bez klíčů' => [
            static fn (array $s): array => [
                'endpoint' => $s['endpoint'],
            ],
        ];
        yield 'endpoint není text' => [
            static fn (array $s): array => [
                'endpoint' => ['x'],
            ] + $s,
        ];
        yield 'cizí endpoint' => [
            static fn (array $s): array => [
                'endpoint' => 'https://example.com/push',
            ] + $s,
        ];
        yield 'krátký klíč' => [
            static fn (array $s): array => [
                'keys' => [
                    'p256dh' => self::base64Url(random_bytes(32)),
                ] + $s['keys'],
            ] + $s,
        ];
        yield 'klíč není bod křivky' => [
            static fn (array $s): array => [
                'keys' => [
                    'p256dh' => self::base64Url("\x02" . random_bytes(64)),
                ] + $s['keys'],
            ] + $s,
        ];
        yield 'klíč není base64url' => [
            static fn (array $s): array => [
                'keys' => [
                    'p256dh' => 'tohle není klíč!',
                ] + $s['keys'],
            ] + $s,
        ];
        yield 'krátký auth' => [
            static fn (array $s): array => [
                'keys' => [
                    'auth' => self::base64Url(random_bytes(8)),
                ] + $s['keys'],
            ] + $s,
        ];
        yield 'auth není text' => [
            static fn (array $s): array => [
                'keys' => [
                    'auth' => 123,
                ] + $s['keys'],
            ] + $s,
        ];
    }

    public function testDoLoguJdeZEndpointuJenHostitel(): void
    {
        self::assertSame(
            'fcm.googleapis.com',
            OvereniSubscription::getHostitel('https://FCM.googleapis.com/fcm/send/tajna-cast-adresy'),
        );
        self::assertSame('', OvereniSubscription::getHostitel('tohle není adresa'));
    }
}
