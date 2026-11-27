<?php

declare(strict_types=1);

namespace Gamecon\PushNotifikace;

use GuzzleHttp\Psr7\HttpFactory;
use Minishlink\WebPush\ContentEncoding;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Http\Client\ClientInterface;
use Psr\Log\NullLogger;

/**
 * Pošle notifikaci na všechna zařízení uživatele a vrátí, jak to u každého dopadlo.
 *
 * O adminu ani o druhu události neví nic: dostane příjemce a {@see ZpravaNotifikace}. Stejně ho proto zavolá
 * formulář v adminu, cron i fronta. Posílá synchronně, jedno zařízení po druhém, na to stačí hrstka zařízení jednoho
 * uživatele; hromadné rozesílání bude chtít frontu a souběžné požadavky.
 *
 * „Předáno“ znamená, že zprávu převzala push služba prohlížeče. Jestli ji zařízení ukáže, server neví.
 */
final readonly class PushOdesilac
{
    /**
     * @param ClientInterface $httpKlient klient, který nenásleduje přesměrování a má časový limit,
     *                                    viz {@see PushNotifikace::vytvorHttpKlienta()}
     */
    public function __construct(
        private VapidKlice $vapidKlice,
        private PushSubscriptions $subscriptions,
        private ZaznamOdeslani $zaznamOdeslani,
        private ClientInterface $httpKlient,
        private string $urlWebu,
    ) {
    }

    /**
     * @param int|null $idOdesilatele kdo posílá, null když systém (cron); jen do záznamu, právo ani limit tu nikdo
     *                                nekontroluje, to dělá ten, kdo volá ({@see OdeslaniZAdminu})
     *
     * @return list<VysledekOdeslani> jeden výsledek na zařízení, prázdné když uživatel žádné nemá
     */
    public function posliUzivateli(int $idPrijemce, ZpravaNotifikace $zprava, ?int $idOdesilatele = null): array
    {
        $vysledky = [];
        $kOdeslani = [];
        foreach ($this->subscriptions->getAktivniUzivatele($idPrijemce) as $subscription) {
            // Seznam push služeb se mohl zúžit od doby, kdy řádek vznikl, a řádek mohl někdo zapsat mimo aplikaci.
            if (! OvereniSubscription::isEndpointPovolen($subscription->endpoint)) {
                $this->subscriptions->zaznamenejChybu($subscription->id);
                $vysledky[$subscription->id] = $this->vytvorVysledek($subscription, StavOdeslani::CHYBA, null, 'neznámá push služba');
                continue;
            }
            $kOdeslani[] = $subscription;
        }

        foreach ($this->odesli($kOdeslani, $zprava) as $idSubscription => $vysledek) {
            $vysledky[$idSubscription] = $vysledek;
        }
        ksort($vysledky);
        $vysledky = array_values($vysledky);
        $this->zaznamOdeslani->zaznamenej($idOdesilatele, $idPrijemce, $zprava->typ, $vysledky);

        return $vysledky;
    }

    /**
     * @param list<PushSubscription> $subscriptions
     *
     * @return array<int, VysledekOdeslani> podle id subscription
     */
    private function odesli(array $subscriptions, ZpravaNotifikace $zprava): array
    {
        if ($subscriptions === []) {
            return [];
        }
        $tovarna = new HttpFactory();
        $webPush = new WebPush(
            auth: [
                'VAPID' => $this->vapidKlice->getKonfiguraceKnihovny(),
            ],
            defaultOptions: [
                'TTL'     => $zprava->ttl,
                'urgency' => $zprava->urgence->value,
                // Zprávu se stejným tématem, která na zařízení ještě nedošla, push služba nahradí novou.
                'topic' => $zprava->tag,
            ],
            client: $this->httpKlient,
            requestFactory: $tovarna,
            streamFactory: $tovarna,
            // knihovna by jinak varování o chybějících rozšířeních hlásila přes trigger_error()
            logger: new NullLogger(),
        );
        $payload = $zprava->getPayload($this->urlWebu);

        $vysledky = [];
        foreach ($subscriptions as $subscription) {
            try {
                $hlaseni = $webPush->sendOneNotification(
                    new Subscription(
                        $subscription->endpoint,
                        $subscription->p256dh,
                        $subscription->auth,
                        ContentEncoding::aes128gcm,
                    ),
                    $payload,
                );
            } catch (\Throwable) {
                // Klíč zařízení, se kterým nejde šifrovat. Jedno vadné zařízení nesmí zastavit odeslání ostatním;
                // zpráva výjimky se nezapisuje, mohla by nést klíč.
                $this->subscriptions->zaznamenejChybu($subscription->id);
                $vysledky[$subscription->id] = $this->vytvorVysledek(
                    $subscription,
                    StavOdeslani::CHYBA,
                    null,
                    'zprávu se pro zařízení nepodařilo zašifrovat',
                );
                continue;
            }
            $vysledky[$subscription->id] = $this->zpracujHlaseni($subscription, $hlaseni);
        }

        return $vysledky;
    }

    private function zpracujHlaseni(PushSubscription $subscription, MessageSentReport $hlaseni): VysledekOdeslani
    {
        $httpKod = $hlaseni->getResponse()?->getStatusCode();
        if ($hlaseni->isSuccess()) {
            $this->subscriptions->zaznamenejPredani($subscription->id);

            return $this->vytvorVysledek($subscription, StavOdeslani::PREDANO, $httpKod);
        }
        if ($hlaseni->isSubscriptionExpired()) {
            $this->subscriptions->smaz($subscription->id);

            return $this->vytvorVysledek($subscription, StavOdeslani::NEPLATNA_SMAZANA, $httpKod, 'push služba subscription nezná');
        }
        $this->subscriptions->zaznamenejChybu($subscription->id);

        // Zprávu výjimky ani tělo odpovědi dál neposíláme: klient do nich píše celou adresu, tedy endpoint.
        return $this->vytvorVysledek(
            $subscription,
            StavOdeslani::CHYBA,
            $httpKod,
            $httpKod === null
                ? 'push služba neodpověděla'
                : self::getDuvodPodleKodu($httpKod),
        );
    }

    private static function getDuvodPodleKodu(int $httpKod): string
    {
        return match (true) {
            $httpKod === 400 => 'push služba zprávu odmítla jako neplatnou',
            $httpKod === 401, $httpKod === 403 => 'push služba odmítla podpis: subscription vznikla s jinými VAPID klíči',
            $httpKod === 413 => 'zpráva je moc velká',
            $httpKod === 429 => 'push služba omezuje počet zpráv, zkus to později',
            $httpKod >= 500  => 'push služba má potíže, zkus to později',
            default          => 'push služba odpověděla nečekaně',
        };
    }

    private function vytvorVysledek(
        PushSubscription $subscription,
        StavOdeslani $stav,
        ?int $httpKod,
        string $duvod = '',
    ): VysledekOdeslani {
        return new VysledekOdeslani(
            idSubscription: $subscription->id,
            zarizeni: $subscription->zarizeni,
            pushSluzba: $subscription->getPushSluzba(),
            stav: $stav,
            httpKod: $httpKod,
            duvod: $duvod,
        );
    }
}
