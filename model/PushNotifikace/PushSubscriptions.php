<?php

declare(strict_types=1);

namespace Gamecon\PushNotifikace;

use Gamecon\Uzivatel\TrvalePrihlaseni;

/**
 * Úložiště subscriptions (tabulka `uzivatele_push_subscriptions`), jeden řádek na prohlížeč.
 *
 * Řádek patří k trvalému přihlášení zařízení a databáze ho s ním sama maže (ON DELETE CASCADE): odhlášení,
 * odhlášení ostatních zařízení, změna a obnova hesla i sloučení účtů tak notifikace ukončí bez dalšího kódu,
 * viz docs/generated/web-push-notifikace.md.
 *
 * Endpoint je unikátní, ale do unikátního klíče se nevejde (délku mu nikdo neomezuje), proto se hledá přes
 * `endpoint_hash`, celé SHA-256, které počítá databáze.
 *
 * Každý řádek nese VAPID klíč a adresu webu prostředí, kde vznikl. Beta a preview běží nad kopií ostré databáze
 * a {@see self::getAktivniUzivatele()} jim vydá jen jejich vlastní řádky, takže zařízením z ostré nepošlou nic.
 */
final readonly class PushSubscriptions
{
    /**
     * Víc zařízení jeden člověk nemá; strop je proti zaplnění tabulky jedním účtem.
     */
    public const int MAX_ZARIZENI_UZIVATELE = 20;

    /**
     * Chyba MariaDB „Cannot add or update a child row“: trvalé přihlášení, ke kterému má subscription patřit, mezitím
     * někdo zrušil (odhlášení v jiné kartě, „Odhlásit ostatní zařízení“).
     */
    private const int CHYBI_RADEK_CIZIHO_KLICE = 1452;

    /**
     * @param string $vapidVerejnyKlic veřejný VAPID klíč tohoto prostředí
     * @param string $origin           adresa webu tohoto prostředí (URL_WEBU)
     */
    public function __construct(
        private string $vapidVerejnyKlic,
        private string $origin,
    ) {
    }

    /**
     * Zapne notifikace na zařízení. Endpoint, který už existuje, se přepíše: stejné zařízení se registruje znovu,
     * nebo si na něm notifikace zapnul jiný uživatel, a pak předchozímu chodit přestanou.
     *
     * @param array{endpoint: string, p256dh: string, auth: string} $subscription z {@see OvereniSubscription::over()}
     *
     * @throws ChybaPushNotifikace když už má uživatel zařízení moc
     */
    public function uloz(
        int $idUzivatele,
        int $idTrvalehoPrihlaseni,
        #[\SensitiveParameter]
        array $subscription,
        string $zarizeni,
    ): void {
        $existujici = $this->najdiPodleEndpointu($subscription['endpoint']);
        if (($existujici === null || $existujici->idUzivatele !== $idUzivatele)
            && $this->getPocetUzivatele($idUzivatele) >= self::MAX_ZARIZENI_UZIVATELE
        ) {
            throw new ChybaPushNotifikace('Notifikace už máš zapnuté na příliš mnoha zařízeních. Vypni je na některém, které nepoužíváš, nebo v Nastavení odhlas ostatní zařízení.');
        }
        $parametry = [
            0 => $idUzivatele,
            1 => $idTrvalehoPrihlaseni,
            2 => $subscription['endpoint'],
            3 => $subscription['p256dh'],
            4 => $subscription['auth'],
            5 => $this->vapidVerejnyKlic,
            6 => $this->origin,
            7 => mb_substr($zarizeni, 0, 255),
        ];
        try {
            dbQuery(<<<'SQL'
                INSERT INTO uzivatele_push_subscriptions
                    (id_uzivatele, id_trvaleho_prihlaseni, endpoint, p256dh, auth, vapid_verejny_klic, origin, zarizeni)
                VALUES ($0, $1, $2, $3, $4, $5, $6, $7)
                ON DUPLICATE KEY UPDATE
                    id_uzivatele = VALUES(id_uzivatele),
                    id_trvaleho_prihlaseni = VALUES(id_trvaleho_prihlaseni),
                    p256dh = VALUES(p256dh),
                    auth = VALUES(auth),
                    vapid_verejny_klic = VALUES(vapid_verejny_klic),
                    origin = VALUES(origin),
                    zarizeni = VALUES(zarizeni),
                    aktualizovano = NOW(),
                    pocet_chyb_v_rade = 0
                SQL, $parametry);
        } catch (\DbException $chyba) {
            throw self::vytvorChybuBezDotazu((int) $chyba->getCode());
        }
    }

    /**
     * Subscription podle endpointu, ať je kohokoli a z kteréhokoli prostředí.
     */
    public function najdiPodleEndpointu(#[\SensitiveParameter] string $endpoint): ?PushSubscription
    {
        try {
            $radek = dbOneLine(<<<'SQL'
                SELECT uzivatele_push_subscriptions.*
                FROM uzivatele_push_subscriptions
                WHERE uzivatele_push_subscriptions.endpoint_hash = SHA2($0, 256)
                SQL, [
                0 => $endpoint,
            ]);
        } catch (\DbException $chyba) {
            throw self::vytvorChybuBezDotazu((int) $chyba->getCode());
        }

        return $radek
            ? PushSubscription::zRadku($radek)
            : null;
    }

    /**
     * Je subscription s tímto endpointem zapnutá právě pro tohoto uživatele, toto jeho přihlášení a toto prostředí?
     */
    public function isAktivni(
        #[\SensitiveParameter]
        string $endpoint,
        int $idUzivatele,
        int $idTrvalehoPrihlaseni,
    ): bool {
        $parametry = [
            0 => $endpoint,
            1 => $idUzivatele,
            2 => $idTrvalehoPrihlaseni,
            3 => $this->vapidVerejnyKlic,
            4 => $this->origin,
        ];

        try {
            return dbOneCol(<<<'SQL'
                SELECT 1
                FROM uzivatele_push_subscriptions
                WHERE endpoint_hash = SHA2($0, 256)
                    AND id_uzivatele = $1
                    AND id_trvaleho_prihlaseni = $2
                    AND vapid_verejny_klic = $3
                    AND origin = $4
                SQL, $parametry) !== null;
        } catch (\DbException $chyba) {
            throw self::vytvorChybuBezDotazu((int) $chyba->getCode());
        }
    }

    /**
     * Zařízení uživatele, kterým toto prostředí smí poslat: jen ta, která se zapnula tady (stejný VAPID klíč i adresa
     * webu). Řádky z jiného prostředí, které přišly s kopií databáze, se nevydají a nic se jim nepošle.
     *
     * Trvalé přihlášení nepoužité déle než rok už nepřihlásí, jeho řádek ale mizí až s úklidem při něčím dalším
     * přihlášení. Notifikace takového zařízení končí hned, ne až s úklidem.
     *
     * @return list<PushSubscription>
     */
    public function getAktivniUzivatele(int $idUzivatele): array
    {
        $radky = dbFetchAll(<<<'SQL'
            SELECT uzivatele_push_subscriptions.*
            FROM uzivatele_push_subscriptions
            JOIN uzivatele_trvala_prihlaseni
                ON uzivatele_trvala_prihlaseni.id = uzivatele_push_subscriptions.id_trvaleho_prihlaseni
            WHERE uzivatele_push_subscriptions.id_uzivatele = $0
                AND uzivatele_push_subscriptions.vapid_verejny_klic = $1
                AND uzivatele_push_subscriptions.origin = $2
                AND uzivatele_trvala_prihlaseni.naposledy_pouzito >= NOW() - INTERVAL $3 DAY
            ORDER BY uzivatele_push_subscriptions.id
            SQL, [
            0 => $idUzivatele,
            1 => $this->vapidVerejnyKlic,
            2 => $this->origin,
            3 => TrvalePrihlaseni::PLATNOST_DNU,
        ]);

        return array_map(PushSubscription::zRadku(...), $radky);
    }

    /**
     * Vypne notifikace na zařízení, jen vlastní.
     */
    public function smazUzivateli(int $idUzivatele, #[\SensitiveParameter] string $endpoint): void
    {
        try {
            dbQuery(<<<'SQL'
                DELETE FROM uzivatele_push_subscriptions
                WHERE endpoint_hash = SHA2($0, 256) AND id_uzivatele = $1
                SQL, [
                0 => $endpoint,
                1 => $idUzivatele,
            ]);
        } catch (\DbException $chyba) {
            throw self::vytvorChybuBezDotazu((int) $chyba->getCode());
        }
    }

    public function smaz(int $id): void
    {
        dbQuery(<<<'SQL'
            DELETE FROM uzivatele_push_subscriptions
            WHERE id = $0
            SQL, [
            0 => $id,
        ]);
    }

    /**
     * Všechna zařízení uživatele, ze všech prostředí: při anonymizaci účtu. Endpoint je osobní údaj a anonymní účet
     * už nemá dostávat nic.
     */
    public static function smazVsechnyUzivatele(int $idUzivatele, ?\mysqli $mysqli = null): void
    {
        dbQuery(<<<'SQL'
            DELETE FROM uzivatele_push_subscriptions
            WHERE id_uzivatele = $0
            SQL, [
            0 => $idUzivatele,
        ], $mysqli);
    }

    /**
     * Push služba zprávu přijala. Jestli ji zařízení ukázalo, server neví.
     */
    public function zaznamenejPredani(int $id): void
    {
        dbQuery(<<<'SQL'
            UPDATE uzivatele_push_subscriptions
            SET naposledy_predano = NOW(), pocet_chyb_v_rade = 0
            WHERE id = $0
            SQL, [
            0 => $id,
        ]);
    }

    public function zaznamenejChybu(int $id): void
    {
        dbQuery(<<<'SQL'
            UPDATE uzivatele_push_subscriptions
            SET pocet_chyb_v_rade = LEAST(pocet_chyb_v_rade + 1, 65535)
            WHERE id = $0
            SQL, [
            0 => $id,
        ]);
    }

    /**
     * Chyba místo {@see \DbException} u dotazu, jehož text nese endpoint nebo klíče zařízení. Původní výjimku by
     * aplikace uložila mezi zachycené chyby i s celým dotazem a s argumenty volání; z téhle se pozná jen číslo chyby.
     * Proto dostává jen to číslo, ne výjimku samu, a nenese ji ani jako `previous`.
     */
    private static function vytvorChybuBezDotazu(int $kodChybyDatabaze): ChybaPushNotifikace
    {
        return $kodChybyDatabaze === self::CHYBI_RADEK_CIZIHO_KLICE
            ? new ChybaPushNotifikace('Přihlášení na tomto zařízení mezitím skončilo, přihlas se prosím znovu.', 409)
            : new ChybaPushNotifikace("Notifikace se nepodařilo uložit (chyba databáze {$kodChybyDatabaze}), zkus to prosím za chvíli.", 503);
    }

    private function getPocetUzivatele(int $idUzivatele): int
    {
        return (int) dbOneCol(<<<'SQL'
            SELECT COUNT(*)
            FROM uzivatele_push_subscriptions
            WHERE id_uzivatele = $0
            SQL, [
            0 => $idUzivatele,
        ]);
    }
}
