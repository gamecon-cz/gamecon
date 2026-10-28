<?php

declare(strict_types=1);

namespace Gamecon\Dev;

/**
 * Odpověď admina na dotaz brány preview „je tahle relace přihlášený organizátor?" (`/api/preview-vstup`).
 * Volat smí jen gate-validator s krátce platným tokenem v `X-Gate-Auth` (odkaz `?gate=` na 24 h nestačí), ostatním
 * se endpoint tváří, že neexistuje. Proč a jak: docs/generated/preview-vstup-pres-adminu.md.
 */
final class PreviewVstup
{
    /**
     * Validator podepisuje token na minutu, dvojnásobek je rezerva na hodiny.
     */
    public const NEJDELSI_PLATNOST_TOKENU_SEKUND = 120;

    public function __construct(
        private readonly string $gateSecret,
        private readonly string $ssoMaster,
    ) {
    }

    public function jeVolaniOdValidatoru(string $gateAuth, ?int $ted = null): bool
    {
        return GateLink::over($gateAuth, $this->gateSecret, $ted, self::NEJDELSI_PLATNOST_TOKENU_SEKUND);
    }

    /**
     * Do preview smí organizátor letošního ročníku a ten, kdo spravuje web (kdo na stránce Previews
     * vidí i přístupové údaje). Vstup do preview neotvírá nic navíc: oprávnění v aplikaci preview se řídí
     * rolemi uživatele stejně jako na ostré.
     *
     * @return array{0: int, 1: array<string, string>} HTTP status a tělo odpovědi
     */
    public function odpovez(
        ?int $idUzivatele,
        bool $jeOrganizator,
        bool $maPravoNaAdministraciWebu,
        string $nonce,
        ?int $ted = null,
    ): array {
        if ($idUzivatele === null) {
            return [401, ['stav' => 'neprihlasen']];
        }
        if (! $jeOrganizator && ! $maPravoNaAdministraciWebu) {
            return [403, ['stav' => 'bez-pristupu']];
        }

        return [200, ['stav' => 'ok', 'gcsso' => $this->podepisLogin($idUzivatele, $nonce, $ted)]];
    }

    /**
     * Prázdný token znamená „pustit dál, ale nepřihlašovat do preview": bez masteru ho nejde podepsat
     * a nonce, které není ve tvaru spárovací cookie, by token nikdy neověřilo.
     */
    private function podepisLogin(int $idUzivatele, string $nonce, ?int $ted): string
    {
        if ($this->ssoMaster === '' || preg_match('~^[0-9a-f]{32}\z~', $nonce) !== 1) {
            return '';
        }

        return CrossSiteLogin::podepis($idUzivatele, $nonce, $this->ssoMaster, $ted);
    }
}
