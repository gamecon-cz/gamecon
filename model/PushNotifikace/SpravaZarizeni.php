<?php

declare(strict_types=1);

namespace Gamecon\PushNotifikace;

/**
 * Zapnutí, vypnutí a stav notifikací na zařízení přihlášeného uživatele. Volá ji API webu
 * (web/moduly/api/pushSubscription.php) za prohlížeč a za service worker.
 *
 * Pravidla:
 * - Notifikace patří k trvalému přihlášení zařízení. Bez něj zapnout nejdou.
 * - Admin přepnutý na cizí účet nezapne ani nezruší nic: zařízení je jeho, účet ne.
 * - Endpoint, který prohlížeč má, ale server ho pro tohoto uživatele a toto přihlášení nezná, patří předchozímu
 *   uživateli zařízení nebo skončenému přihlášení. Server jeho řádek smaže a prohlížeči řekne, ať subscription zruší.
 */
final readonly class SpravaZarizeni
{
    public function __construct(
        private PushSubscriptions $subscriptions,
        private ZaznamOdeslani $zaznamOdeslani,
    ) {
    }

    /**
     * @param mixed $subscription co poslal prohlížeč, `PushSubscription.toJSON()`
     *
     * @throws ChybaPushNotifikace
     */
    public function zapni(KontextZarizeni $kontext, #[\SensitiveParameter] mixed $subscription, string $userAgent): void
    {
        $idTrvalehoPrihlaseni = $this->getIdTrvalehoPrihlaseni($kontext);
        $overena = $this->over($kontext, $subscription);
        $this->subscriptions->uloz(
            $kontext->idUzivatele,
            $idTrvalehoPrihlaseni,
            $overena,
            PopisZarizeni::zUserAgenta($userAgent),
        );
    }

    /**
     * @throws ChybaPushNotifikace
     */
    public function vypni(KontextZarizeni $kontext, #[\SensitiveParameter] mixed $endpoint): void
    {
        if ($kontext->prepnuto) {
            throw self::vytvorChybuPrepnuto();
        }
        $endpoint = self::getEndpointZPozadavku($endpoint);
        if ($endpoint !== null) {
            $this->subscriptions->smazUzivateli($kontext->idUzivatele, $endpoint);
        }
    }

    /**
     * @param mixed $endpoint endpoint subscription, kterou prohlížeč má, null když žádnou
     *
     * @return array{stav: StavZarizeni, zrusitVProhlizeci: bool} zrusitVProhlizeci: prohlížeč má zrušit svou
     *                                                            subscription, server ji pro tohoto uživatele nezná
     */
    public function zjistiStav(KontextZarizeni $kontext, #[\SensitiveParameter] mixed $endpoint): array
    {
        if ($kontext->prepnuto) {
            // Subscription v prohlížeči je toho, kdo se přepnul. Nesahat na ni ani na její řádek.
            return [
                'stav'              => StavZarizeni::PREPNUTO,
                'zrusitVProhlizeci' => false,
            ];
        }
        $endpoint = self::getEndpointZPozadavku($endpoint);
        if ($endpoint !== null
            && $kontext->idTrvalehoPrihlaseni !== null
            && $this->subscriptions->isAktivni($endpoint, $kontext->idUzivatele, $kontext->idTrvalehoPrihlaseni)
        ) {
            return [
                'stav'              => StavZarizeni::ZAPNUTO,
                'zrusitVProhlizeci' => false,
            ];
        }
        if ($endpoint !== null) {
            // Kdo endpoint zná, drží zařízení: předchozímu uživateli na něm notifikace chodit přestanou.
            $ciziNeboStara = $this->subscriptions->najdiPodleEndpointu($endpoint);
            if ($ciziNeboStara !== null) {
                $this->subscriptions->smaz($ciziNeboStara->id);
            }
        }

        return [
            'stav' => $kontext->idTrvalehoPrihlaseni === null
                ? StavZarizeni::BEZ_TRVALEHO_PRIHLASENI
                : StavZarizeni::VYPNUTO,
            'zrusitVProhlizeci' => $endpoint !== null,
        ];
    }

    /**
     * Prohlížeč vyměnil subscription za novou (událost `pushsubscriptionchange` v service workeru). Novou přijme jen
     * místo té, kterou server pro tohoto uživatele a toto přihlášení zná: service worker tak nic nezapne sám od sebe.
     *
     * @throws ChybaPushNotifikace
     */
    public function zmen(
        KontextZarizeni $kontext,
        #[\SensitiveParameter]
        mixed $staryEndpoint,
        #[\SensitiveParameter]
        mixed $subscription,
        string $userAgent,
    ): void {
        $idTrvalehoPrihlaseni = $this->getIdTrvalehoPrihlaseni($kontext);
        $staryEndpoint = self::getEndpointZPozadavku($staryEndpoint);
        if ($staryEndpoint === null
            || ! $this->subscriptions->isAktivni($staryEndpoint, $kontext->idUzivatele, $idTrvalehoPrihlaseni)
        ) {
            throw new ChybaPushNotifikace('Notifikace na tomto zařízení nejsou zapnuté.', 409);
        }
        $overena = $this->over($kontext, $subscription);
        $this->subscriptions->smazUzivateli($kontext->idUzivatele, $staryEndpoint);
        $this->subscriptions->uloz(
            $kontext->idUzivatele,
            $idTrvalehoPrihlaseni,
            $overena,
            PopisZarizeni::zUserAgenta($userAgent),
        );
    }

    /**
     * @throws ChybaPushNotifikace
     */
    private function getIdTrvalehoPrihlaseni(KontextZarizeni $kontext): int
    {
        if ($kontext->prepnuto) {
            throw self::vytvorChybuPrepnuto();
        }
        if ($kontext->idTrvalehoPrihlaseni === null) {
            throw new ChybaPushNotifikace('Notifikace jdou zapnout jen na zařízení, kde jsi přihlášen(a) trvale. Odhlas se a přihlas znovu se zaškrtnutým „Trvale přihlásit“.', 409);
        }

        return $kontext->idTrvalehoPrihlaseni;
    }

    /**
     * @return array{endpoint: string, p256dh: string, auth: string}
     *
     * @throws ChybaPushNotifikace
     */
    private function over(KontextZarizeni $kontext, #[\SensitiveParameter] mixed $subscription): array
    {
        $endpoint = is_array($subscription)
            ? ($subscription['endpoint'] ?? null)
            : null;
        if (is_string($endpoint) && ! OvereniSubscription::isEndpointPovolen($endpoint)) {
            $this->zaznamOdeslani->zaznamenejOdmitnutyEndpoint(
                $kontext->idUzivatele,
                OvereniSubscription::getHostitel(substr($endpoint, 0, OvereniSubscription::MAX_DELKA_ENDPOINTU)),
            );
        }

        return OvereniSubscription::over($subscription);
    }

    /**
     * Endpoint z těla požadavku, jen když vede na známou push službu: jiný v tabulce být nemůže, a do dotazu se tak
     * nedostane nic, co napsal útočník (text, který vypadá jako číslo, znaky mimo ASCII).
     */
    private static function getEndpointZPozadavku(#[\SensitiveParameter] mixed $endpoint): ?string
    {
        return is_string($endpoint) && OvereniSubscription::isEndpointPovolen($endpoint)
            ? $endpoint
            : null;
    }

    private static function vytvorChybuPrepnuto(): ChybaPushNotifikace
    {
        return new ChybaPushNotifikace(
            'Jsi přepnutý(á) na cizí účet. Jeho notifikace na svém zařízení zapnout ani vypnout nemůžeš.',
            403,
        );
    }
}
