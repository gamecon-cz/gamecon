<?php

declare(strict_types=1);

namespace Gamecon\Dev;

/**
 * Proklik z adminu do Vševědy (finanční dotazy nad kopií ostré DB, repo gamecon-cz/vseveda), která
 * přihlašuje stejným `?gcsso=` tokenem jako archivy. Podepisuje se vlastním tajemstvím VSEVEDA_SSO_SECRET,
 * ne masterem GAMECON_SSO_SECRET: ten dostává každé preview, takže by si s ním přihlášení podepsala
 * libovolná větev. Kdo smí dovnitř, rozhoduje až Vševěda podle svého seznamu uživatelů.
 */
final class VsevedaOdkaz
{
    public const URL_PRIHLASENI = 'https://vseveda.preview.gamecon.cz/prihlaseni';

    /**
     * Null, když chybí tajemství: bez něj by odkaz nikoho nepřihlásil, tak ho raději nenabízíme.
     */
    public static function url(
        int $idUzivatele,
        string $nonce,
        string $vsevedaSecret,
        string $gateSecret,
        ?int $ted = null,
    ): ?string {
        $gcsso = CrossSiteLogin::podepis($idUzivatele, $nonce, $vsevedaSecret, $ted);
        if ($gcsso === '') {
            return null;
        }

        return GateLink::podepis(self::URL_PRIHLASENI . '?gcsso=' . $gcsso, $gateSecret, $ted);
    }
}
