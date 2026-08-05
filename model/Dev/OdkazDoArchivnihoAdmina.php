<?php

declare(strict_types=1);

namespace Gamecon\Dev;

/**
 * Staví odkaz z ostrého adminu na TÉHOŽ uživatele v adminu archivního ročníku.
 *
 * Skládá dohromady tři nezávislé mechanismy, které musí na odkazu být všechny
 * najednou, jinak proklik skončí na dialogu nebo na přihlašovací obrazovce:
 *
 *   1. `?gate=`             — Caddy brána před archivy chce basic auth. Vložené
 *                             `user:heslo@host` prohlížeč při kliknutí zahodí,
 *                             takže se místo toho podepíše gate token
 *                             ({@see GateLink}), který gate-validator vymění za
 *                             session cookie.
 *   2. `?gcsso=`            — magické přihlášení do archivu ({@see CrossSiteLogin}),
 *                             podepsané klíčem ODVOZENÝM pro daný ročník z master
 *                             tajemství. Párovací cookie ({@see SsoParovaciCookie})
 *                             zajistí, že sdílený odkaz nikoho nepřihlásí.
 *   3. `?pracovni_uzivatel=` — až tohle v archivu otevře konkrétního uživatele.
 *
 * Pořadí zpracování v archivu: `admin/scripts/prihlaseni.php` vyřídí nejdřív
 * `gcsso` a přesměruje `back(getCurrentUrlWithQuery(['gcsso' => null]))`, což
 * ostatní parametry v query zachová — `pracovni_uzivatel` tedy přežije do druhého
 * requestu, kde už je uživatel přihlášený a nastaví se pracovní uživatel.
 * Proto smí být oba parametry na jedné URL.
 *
 * Archivy neběží na `admin.ROK.gamecon.cz` — každý ročník je jeden kontejner,
 * kde admin sedí na cestě `/admin` stejně jako v lokálním vývoji.
 *
 * Ročníky neumí totéž, takže odkaz degraduje místo aby zmizel — i „jen do adminu"
 * je lepší než nic, protože ušetří hledání hostname a projde přes bránu.
 */
final class OdkazDoArchivnihoAdmina
{
    /**
     * Od tohoto ročníku výš umí archivní admin přepnout pracovního uživatele
     * z URL (`?pracovni_uzivatel=`). Starší ročníky ten parametr vůbec nečtou,
     * takže u nich odkaz vede jen do adminu a uživatele si musíš vyhledat ručně.
     */
    public const PRVNI_ROCNIK_S_PRACOVNIM_UZIVATELEM = 2022;

    /**
     * Od tohoto ročníku výš umí archiv magické přihlášení (`?gcsso=`). 2012–2013
     * mají starší layout (`admin/` + `sdilene/`, `get()` vrací `''` místo `null`)
     * a portovat ho tam se nevyplatilo — odkaz na ně vede na přihlašovací obrazovku.
     */
    public const PRVNI_ROCNIK_SE_SSO = 2014;

    /**
     * Od tohoto ročníku výš je archiv živá PHP aplikace s adminem. Starší roky
     * jsou statické kopie z Internet Archive, kde žádný admin neexistuje.
     */
    public const PRVNI_ROCNIK_SE_ZIVYM_ADMINEM = 2011;

    /**
     * @var array<int,string> ročník → URL archivu, jen reálně nasazené ročníky
     */
    private readonly array $urlPodleRocniku;

    /**
     * @param list<Archive> $archivy    nasazené archivní ročníky
     * @param string        $ssoMaster  master tajemství pro magické přihlášení; prázdné = SSO se nepřipojí
     * @param string        $gateSecret tajemství pro bránu; prázdné = odkaz zůstane na čisté URL
     * @param string|null   $ssoNonce   nonce spárovaný s cookie; null = SSO se nepřipojí
     */
    public function __construct(
        array $archivy,
        private readonly string $ssoMaster = '',
        private readonly string $gateSecret = '',
        private readonly ?string $ssoNonce = null,
    ) {
        $urlPodleRocniku = [];
        foreach ($archivy as $archiv) {
            $urlPodleRocniku[$archiv->year] = $archiv->url;
        }
        $this->urlPodleRocniku = $urlPodleRocniku;
    }

    /**
     * Vrátí URL do adminu daného ročníku, nebo `null` když tam odkázat nejde
     * (archiv není nasazený, nebo v tom roce admin vůbec neexistuje).
     *
     * Odkaz se podle stáří ročníku degraduje ve třech stupních — vždy vede
     * aspoň tam, kam ten který archiv umí:
     *   2022+       rovnou na uživatele, přihlášeného   (`pracovni_uzivatel` + `gcsso`)
     *   2014–2021   do adminu, přihlášeného             (`gcsso`)
     *   2011–2013   do adminu na přihlašovací obrazovku (jen brána)
     */
    public function proUzivatele(int $rocnik, int $idUzivatele): ?string
    {
        if ($rocnik < self::PRVNI_ROCNIK_SE_ZIVYM_ADMINEM) {
            return null;
        }
        $urlArchivu = $this->urlPodleRocniku[$rocnik] ?? null;
        if ($urlArchivu === null) {
            return null;
        }

        $url = rtrim($urlArchivu, '/') . '/admin';
        if ($rocnik >= self::PRVNI_ROCNIK_S_PRACOVNIM_UZIVATELEM) {
            $url .= '/uzivatel?pracovni_uzivatel=' . $idUzivatele;
        }

        if ($rocnik >= self::PRVNI_ROCNIK_SE_SSO
            && $this->ssoNonce !== null
            && $this->ssoMaster !== ''
            && $idUzivatele > 0
        ) {
            // Klíč odvozený pro ročník — archiv nikdy nedostane master, takže
            // z popadnutého archivu nejde podvrhnout přihlášení do jiných ročníků.
            $klicRocniku = hash_hmac('sha256', (string) $rocnik, $this->ssoMaster);
            $gcsso = CrossSiteLogin::podepis($idUzivatele, $this->ssoNonce, $klicRocniku);
            if ($gcsso !== '') {
                $oddelovac = str_contains($url, '?') ? '&' : '?';
                $url .= $oddelovac . 'gcsso=' . $gcsso;
            }
        }

        return GateLink::podepis($url, $this->gateSecret);
    }
}
