<?php

declare(strict_types=1);

namespace Gamecon\PushNotifikace;

use Gamecon\Pravo;

/**
 * Odeslání notifikace z adminu (stránky Push notifikace a Testovací notifikace): kdo smí, komu a jak často.
 * Samotné odeslání dělá {@see PushOdesilac}, který o adminu neví.
 */
final readonly class OdeslaniZAdminu
{
    /**
     * Klíč session, pod kterým si stránka po přesměrování vyzvedne výsledek posledního odeslání.
     */
    private const string SESSION_VYSLEDEK = 'push_notifikace_vysledek';

    public function __construct(
        private PushNotifikace $pushNotifikace,
    ) {
    }

    public static function isPovolen(?\Uzivatel $prihlaseny): bool
    {
        return $prihlaseny !== null && $prihlaseny->maPravo(Pravo::ADMINISTRACE_PUSH_NOTIFIKACE);
    }

    /**
     * @param \Uzivatel|null $prijemce uživatel vybraný v adminu (pracovní uživatel)
     *
     * @return list<VysledekOdeslani> jeden výsledek na zařízení, prázdné když příjemce žádné zapnuté nemá
     *
     * @throws ChybaPushNotifikace
     */
    public function posli(\Uzivatel $odesilatel, ?\Uzivatel $prijemce, ZpravaNotifikace $zprava): array
    {
        if (! self::isPovolen($odesilatel)) {
            throw new ChybaPushNotifikace('Na odesílání push notifikací nemáš právo.', 403);
        }
        // vypnuté notifikace a chybějící klíče prostředí odmítne už odesílač
        $odesilac = $this->pushNotifikace->getOdesilac();
        if ($prijemce === null) {
            throw new ChybaPushNotifikace('Nejdřív nahoře vyber uživatele, kterému se má notifikace poslat.');
        }
        $idOdesilatele = (int) $odesilatel->id();
        $idPrijemce = (int) $prijemce->id();
        $this->pushNotifikace->getZaznamOdeslani()->overLimit($idOdesilatele, $idPrijemce);

        return $odesilac->posliUzivateli($idPrijemce, $zprava, $idOdesilatele);
    }

    /**
     * Přišel požadavek ze stránky adminu? Admin nemá kontrolu jako web (`omezCsrf()`), spoléhá na cookie session
     * se SameSite=Lax. Odeslání notifikace jinému člověku je akce navenek, proto si původ ověřuje ještě samo:
     * prohlížeč posílá u POSTu hlavičku Origin (starší aspoň Referer) a cizí stránka ji nepodvrhne.
     *
     * @param array<string, mixed> $server $_SERVER
     */
    public static function isPozadavekZAdminu(array $server, string $urlAdmin): bool
    {
        $puvod = $server['HTTP_ORIGIN'] ?? $server['HTTP_REFERER'] ?? '';
        if (! is_string($puvod) || $puvod === '') {
            return false;
        }
        $hostitelPuvodu = parse_url($puvod, PHP_URL_HOST);
        $hostitelAdminu = parse_url($urlAdmin, PHP_URL_HOST);

        return is_string($hostitelPuvodu)
            && is_string($hostitelAdminu)
            && strcasecmp($hostitelPuvodu, $hostitelAdminu) === 0;
    }

    /**
     * Uloží výsledek odeslání do session, aby ho stránka ukázala po přesměrování (obnovení stránky tak nic
     * neodešle znovu).
     *
     * @param list<VysledekOdeslani> $vysledky
     */
    public static function ulozVysledek(string $nadpis, int $idPrijemce, array $vysledky): void
    {
        $_SESSION[self::SESSION_VYSLEDEK] = [
            'nadpis'     => $nadpis,
            'idPrijemce' => $idPrijemce,
            'vysledky'   => array_map(static fn (VysledekOdeslani $vysledek): array => $vysledek->toArray(), $vysledky),
        ];
    }

    /**
     * @return array{nadpis: string, idPrijemce: int, vysledky: list<VysledekOdeslani>}|null výsledek posledního
     *                                                                                       odeslání, jen jednou
     */
    public static function vyzvedniVysledek(): ?array
    {
        $ulozeny = $_SESSION[self::SESSION_VYSLEDEK] ?? null;
        unset($_SESSION[self::SESSION_VYSLEDEK]);
        if (! is_array($ulozeny) || ! is_array($ulozeny['vysledky'] ?? null)) {
            return null;
        }

        return [
            'nadpis'     => (string) ($ulozeny['nadpis'] ?? ''),
            'idPrijemce' => (int) ($ulozeny['idPrijemce'] ?? 0),
            'vysledky'   => array_values(array_filter(array_map(
                static fn (mixed $data): ?VysledekOdeslani => is_array($data)
                    ? VysledekOdeslani::zArray($data)
                    : null,
                $ulozeny['vysledky'],
            ))),
        ];
    }
}
