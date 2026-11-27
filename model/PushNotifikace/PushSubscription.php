<?php

declare(strict_types=1);

namespace Gamecon\PushNotifikace;

/**
 * Jedno zařízení (prohlížeč) uživatele, na které jdou poslat notifikace: řádek `uzivatele_push_subscriptions`.
 */
final readonly class PushSubscription
{
    public function __construct(
        public int $id,
        public int $idUzivatele,
        public int $idTrvalehoPrihlaseni,
        #[\SensitiveParameter]
        public string $endpoint,
        #[\SensitiveParameter]
        public string $p256dh,
        #[\SensitiveParameter]
        public string $auth,
        public string $zarizeni,
        public \DateTimeImmutable $vytvoreno,
        public ?\DateTimeImmutable $naposledyPredano,
        public int $pocetChybVRade,
    ) {
    }

    /**
     * @param array<string, mixed> $radek
     */
    public static function zRadku(array $radek): self
    {
        return new self(
            id: (int) $radek['id'],
            idUzivatele: (int) $radek['id_uzivatele'],
            idTrvalehoPrihlaseni: (int) $radek['id_trvaleho_prihlaseni'],
            endpoint: (string) $radek['endpoint'],
            p256dh: (string) $radek['p256dh'],
            auth: (string) $radek['auth'],
            zarizeni: (string) $radek['zarizeni'],
            vytvoreno: new \DateTimeImmutable((string) $radek['vytvoreno']),
            naposledyPredano: $radek['naposledy_predano'] === null
                ? null
                : new \DateTimeImmutable((string) $radek['naposledy_predano']),
            pocetChybVRade: (int) $radek['pocet_chyb_v_rade'],
        );
    }

    /**
     * Push služba, na kterou endpoint vede. Jediné, co se z endpointu smí ukázat a zapsat do logu.
     */
    public function getPushSluzba(): string
    {
        return OvereniSubscription::getHostitel($this->endpoint);
    }
}
