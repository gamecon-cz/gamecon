<?php

namespace Gamecon\Dev;

/**
 * Ověřovací (consume) strana magického přihlášení do archivu — varianta pro
 * NEJSTARŠÍ éru (2012-2013, framework sdilene/+.hhp).
 *
 * Rozdíl proti novější variantě: NEpoužíváme dbOneCol pro ověření existence
 * uživatele — v téhle éře má dbOneCol jen jeden argument ($q), bez pole parametrů
 * a bez $0 placeholderu. Místo toho voláme rovnou Uzivatel::prihlasId($id), které
 * si samo dělá bezpečný lookup přes dbOneLineS('… WHERE id_uzivatele=$0', array($id))
 * a vrací null, když uživatel s tím ID v téhle DB není (= kdo v daném ročníku ještě
 * účet neměl, se nepřihlásí). Identita je číselné id_uzivatele z tokenu (ne e-mail).
 *
 * Nepracuje s HTTP (žádné $_GET/$_COOKIE/redirect). Viz {@see CrossSiteLogin}
 * (podpis) a {@see SsoParovaciCookie} (spárovací cookie). PHP 5.6-kompatibilní.
 */
final class ArchivSsoPrihlaseni
{
    /** @var string */
    private $secret;

    public function __construct($secret)
    {
        $this->secret = (string) $secret;
    }

    /**
     * Přihlásí uživatele podle tokenu, nebo vrátí $jizPrihlaseny beze změny.
     *
     * @param string         $token
     * @param string|null    $nonceZCookie
     * @param \Uzivatel|null $jizPrihlaseny
     * @return \Uzivatel|null
     */
    public function prihlas($token, $nonceZCookie, $jizPrihlaseny)
    {
        if ($jizPrihlaseny !== null) {
            return $jizPrihlaseny;
        }
        if ($nonceZCookie === null) {
            return null;
        }

        $overene = CrossSiteLogin::over($token, $this->secret);
        if ($overene === null) {
            return null;
        }
        if (! hash_equals($overene->nonce, $nonceZCookie)) {
            return null;
        }

        // prihlasId sám ověří existenci (vrátí null pro neznámé ID) a založí sezení.
        // Klíč 'uzivatel' předáváme explicitně — 2012 ho nemá jako default parametr.
        return \Uzivatel::prihlasId($overene->idUzivatele, 'uzivatel');
    }
}
