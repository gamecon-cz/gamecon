<?php

declare(strict_types=1);

namespace Gamecon\Dev;

/**
 * Ověřovací (consume) strana magického přihlášení do archivu — rozhoduje, zda
 * `?gcsso=` token uživatele přihlásí. Drží pravidla na jednom místě.
 *
 * Identita = číselné `id_uzivatele` z tokenu (ne e-mail — ten je proměnný a mohl by
 * mířit na cizí účet). ID je napříč ostrou i archivními snapshoty stabilní. Ověříme
 * jen, že takový uživatel v TÉHLE archivní DB existuje (kdo v daném ročníku ještě
 * účet neměl, se nenajde → nepřihlásí), a přihlásíme přes Uzivatel::prihlasId
 * (přítomné všude). Přímý dotaz místo Uzivatel::zId, který ve starších ročnících
 * nemusí existovat.
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
     * (= jde o prohlížeč, který klikl — sdílená URL nestačí) a uživatele s tím ID
     * v téhle DB máme. Jinak vrací beze změny (tiché selhání).
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

        $idVDb = dbOneCol(
            'SELECT id_uzivatele FROM uzivatele_hodnoty WHERE id_uzivatele = $0',
            [$overene->idUzivatele],
        );
        if ($idVDb === null) {
            return null;
        }

        return \Uzivatel::prihlasId((int) $idVDb);
    }
}
