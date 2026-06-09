<?php

declare(strict_types=1);

namespace Gamecon\Dev;

/**
 * Ověřovací (consume) strana magického přihlášení do archivu — rozhoduje, zda
 * `?gcsso=` token uživatele přihlásí. Drží pravidla na jednom místě, aby je
 * sdílel procedurální admin/scripts/prihlaseni.php i test.
 *
 * Nepracuje s HTTP (žádné $_GET/$_COOKIE/redirect) — token i nonce z cookie dostane
 * předané; volající si pak řeší odstranění parametru z URL. Viz {@see CrossSiteLogin}
 * (podpis) a {@see SsoParovaciCookie} (spárovací cookie).
 */
final class ArchivSsoPrihlaseni
{
    public function __construct(
        private readonly string $secret,
    ) {
    }

    /**
     * Přihlásí uživatele podle tokenu, nebo vrátí $jizPrihlaseny beze změny.
     *
     * Přihlásí jen když: nikdo tu ještě není přihlášený (cizí sezení na archivu
     * nepřepisujeme), token je platný, jeho nonce sedí s nonce ze spárovací cookie
     * (= jde o prohlížeč, který klikl — sdílená URL nestačí) a uživatele s tím
     * e-mailem v téhle DB máme. Jinak vrací beze změny (tiché selhání).
     *
     * @param string         $token         hodnota `?gcsso=` z URL
     * @param string|null    $nonceZCookie  nonce ze spárovací cookie ({@see SsoParovaciCookie::precti})
     * @param \Uzivatel|null $jizPrihlaseny uživatel už přihlášený v session, nebo null
     */
    public function prihlas(
        string $token,
        ?string $nonceZCookie,
        ?\Uzivatel $jizPrihlaseny,
    ): ?\Uzivatel {
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

        $uzivatel = \Uzivatel::zEmailu($overene->email);
        if ($uzivatel === null) {
            return null;
        }

        return \Uzivatel::prihlasId($uzivatel->id());
    }
}
