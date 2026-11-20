<?php

declare(strict_types=1);

namespace Gamecon\Jazyk;

/**
 * Jazyk požadavku na veřejný web podle prefixu cesty: `/en/program` je anglicky, `/program` česky.
 *
 * Volbu si web pamatuje v cookie prohlížeče: z přepínače `?jazyk=cs|en` vždy, z adresy s prefixem
 * jen při navigaci (viz isNavigace()). U přihlášeného ji ukládá i do `uzivatele_hodnoty.jazyk`
 * (z toho čtou jazyk e-maily, které jdou mimo request), ale jen z přepínače, viz getJazykDoUctu().
 * Kdo si zvolil angličtinu a přijde na českou adresu (odkaz z e-mailu, natvrdo psaný odkaz),
 * přesměruje se na anglickou. Zpátky do češtiny vede přepínač přes `?jazyk=cs`.
 * Podle jazyka prohlížeče se záměrně nepřesměrovává.
 */
final class JazykPozadavku
{
    public const COOKIE = 'gc_jazyk';
    /** Přepínač jazyka: `?jazyk=cs` uloží volbu a přesměruje na tutéž stránku v daném jazyce. */
    public const PARAMETR_PREPNUTI = 'jazyk';

    private static ?self $aktualni = null;

    /**
     * @param array<string, mixed> $dotaz GET parametry kromě routovací `req`
     */
    private function __construct(
        private readonly Jazyk  $jazyk,
        private readonly string $cestaBezJazyka,
        private readonly bool   $maPrefix,
        private readonly array  $dotaz,
    ) {
    }

    /**
     * @param array<string, mixed> $dotaz GET parametry kromě routovací `req`
     */
    public static function zCesty(string $cesta, array $dotaz = []): self
    {
        $cesta = ltrim($cesta, '/');
        foreach (Jazyk::cases() as $jazyk) {
            if ($jazyk->jeVychozi()) {
                continue;
            }
            if ($cesta === $jazyk->value || str_starts_with($cesta, $jazyk->value . '/')) {
                return new self($jazyk, substr($cesta, strlen($jazyk->value) + 1), true, $dotaz);
            }
        }

        return new self(Jazyk::vychozi(), $cesta, false, $dotaz);
    }

    /**
     * Rozpozná jazyk z `$_GET['req']`, odtrhne z něj prefix (routery pak pracují s cestou bez jazyka)
     * a nastaví jazyk požadavku.
     */
    public static function zGlobals(): self
    {
        $dotaz = $_GET;
        unset($dotaz['req']);
        $jazykPozadavku = self::zCesty((string)($_GET['req'] ?? ''), $dotaz);
        $_GET['req'] = $jazykPozadavku->cestaBezJazyka;
        AktualniJazyk::nastav($jazykPozadavku->jazyk);
        self::$aktualni = $jazykPozadavku;

        return $jazykPozadavku;
    }

    public static function aktualni(): ?self
    {
        return self::$aktualni;
    }

    public function jazyk(): Jazyk
    {
        return $this->jazyk;
    }

    public function cestaBezJazyka(): string
    {
        return $this->cestaBezJazyka;
    }

    /**
     * Adresa téže stránky v jiném jazyce. Bez dotazu jde o kanonickou adresu (hreflang).
     */
    public function urlVJazyce(Jazyk $jazyk, bool $sDotazem = true): string
    {
        $dotaz = $sDotazem
            ? $this->dotazBezPrepnuti()
            : [];

        return urlWebu($this->cestaProUrl(), $jazyk)
            . ($dotaz !== []
                ? '?' . http_build_query($dotaz)
                : '');
    }

    /**
     * Odkaz přepínače jazyka. Vede přes `?jazyk=`, aby se volba uložila
     * i při přepnutí do češtiny, kde žádný prefix není.
     */
    public function urlPrepnuti(Jazyk $jazyk): string
    {
        return urlWebu($this->cestaProUrl(), $jazyk)
            . '?' . http_build_query([...$this->dotazBezPrepnuti(), self::PARAMETR_PREPNUTI => $jazyk->value]);
    }

    public function jazykPrepnuti(): ?Jazyk
    {
        $hodnota = $this->dotaz[self::PARAMETR_PREPNUTI] ?? null;

        // ?jazyk[]=… je pole, ne volba jazyka
        return is_string($hodnota)
            ? Jazyk::tryFrom($hodnota)
            : null;
    }

    /**
     * Jestli požadavek načítá stránku do okna prohlížeče (odkaz, zadaná adresa, přesměrování).
     * Formulář, AJAX, `fetch()` ani API to nejsou: z adresy s prefixem si nesmí odvodit volbu jazyka
     * (stará anglická záložka volající `/en/api/…` by ji jinak přepnula zpět) a nepřesměrovávají se
     * (výzva Altchy přes `fetch()` by dostala 302).
     *
     * @param string $secFetchMode hlavička `Sec-Fetch-Mode`, prázdná když chybí: starší prohlížeče a testy
     *                             ji neposílají, pak rozhodne metoda a AJAX
     */
    public function isNavigace(string $metoda, bool $jeAjax, string $secFetchMode = ''): bool
    {
        return ! $jeAjax
            && ($secFetchMode === '' || $secFetchMode === 'navigate')
            && strtoupper($metoda) === 'GET'
            && ! $this->jeApi();
    }

    /**
     * Kam přesměrovat, aby návštěvník zůstal v jazyce, který si zvolil, nebo null, když se zůstává.
     * Přesměrovává se jen navigace, viz isNavigace().
     *
     * @param Jazyk|null $zvoleny volba z cookie, případně uložená u přihlášeného uživatele, viz getZvolenyJazyk()
     */
    public function presmerovani(?Jazyk $zvoleny, string $metoda, bool $jeAjax, string $secFetchMode = ''): ?string
    {
        if (! $this->isNavigace($metoda, $jeAjax, $secFetchMode)) {
            return null;
        }
        $prepnuti = $this->jazykPrepnuti();
        if ($prepnuti !== null) {
            // přepínač: volbu uloží zpracuj(), tady jen odklidíme parametr z adresy
            return $this->urlVJazyce($prepnuti);
        }
        if ($this->maPrefix || $zvoleny === null || $zvoleny === $this->jazyk) {
            return null;
        }

        return $this->urlVJazyce($zvoleny);
    }

    /**
     * Jazyk, který si má zapamatovat cookie prohlížeče, nebo null, když se nemění.
     * Přepínač vždy, adresa s prefixem jazyka jen při navigaci (viz isNavigace()).
     * Česká adresa bez přepínače nic nemění: mohla přijít z natvrdo psaného odkazu.
     */
    public function getJazykDoCookie(string $metoda, bool $jeAjax, string $secFetchMode = ''): ?Jazyk
    {
        $prepnuti = $this->jazykPrepnuti();
        if ($prepnuti !== null) {
            return $prepnuti;
        }

        return $this->maPrefix && $this->isNavigace($metoda, $jeAjax, $secFetchMode)
            ? $this->jazyk
            : null;
    }

    /**
     * Jazyk, který se má uložit přihlášenému do `uzivatele_hodnoty.jazyk`, nebo null, když se nemění.
     * Jen z přepínače: sdílený odkaz na `/en/…` otevřený jednou nesmí přepnout účet (a s ním e-maily
     * i přesměrování na všech zařízeních) a infopult, který se přihlásí jako účastník v prohlížeči
     * s anglickou cookie, nesmí zapsat angličtinu do jeho účtu. Registrace si jazyk ukládá sama.
     */
    public function getJazykDoUctu(): ?Jazyk
    {
        return $this->jazykPrepnuti();
    }

    /**
     * Volba návštěvníka: cookie prohlížeče, jinak jazyk uložený u přihlášeného.
     *
     * @param mixed $cookie hodnota cookie, jak přišla (`gc_jazyk[]=…` je pole)
     */
    public static function getZvolenyJazyk(mixed $cookie, ?Jazyk $jazykUctu): ?Jazyk
    {
        $zCookie = is_string($cookie)
            ? Jazyk::tryFrom($cookie)
            : null;

        return $zCookie ?? $jazykUctu;
    }

    /**
     * Vyřídí přesměrování a zapamatování volby. Volá se na začátku web/index.php.
     */
    public function zpracuj(?\Uzivatel $uzivatel): void
    {
        $metoda = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $jeAjax = is_ajax();
        $secFetchMode = (string) ($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '');

        $doCookie = $this->getJazykDoCookie($metoda, $jeAjax, $secFetchMode);
        if ($doCookie !== null && ($_COOKIE[self::COOKIE] ?? null) !== $doCookie->value) {
            setcookie(self::COOKIE, $doCookie->value, [
                'expires'  => time() + 365 * 24 * 3600,
                'path'     => '/',
                'secure'   => ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            $_COOKIE[self::COOKIE] = $doCookie->value;
        }
        $doUctu = $this->getJazykDoUctu();
        if ($doUctu !== null && $uzivatel !== null && $uzivatel->jazyk() !== $doUctu) {
            $uzivatel->nastavJazyk($doUctu);
        }

        $presmerovani = $this->presmerovani(
            self::getZvolenyJazyk($_COOKIE[self::COOKIE] ?? null, $uzivatel?->jazyk()),
            $metoda,
            $jeAjax,
            $secFetchMode,
        );
        if ($presmerovani !== null) {
            header('Location: ' . $presmerovani, true, 302);
            exit;
        }
    }

    /**
     * Cesta je z `$_GET['req']`, tedy už dekódovaná. Do odkazu i do hlavičky `Location` ji vracíme
     * zakódovanou, jinak by se `%3F` z cesty stal začátkem dotazu, `%0D%0A` by rozbil hlavičku
     * a uvozovka by vyskočila z atributu `href`.
     */
    private function cestaProUrl(): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $this->cestaBezJazyka)));
    }

    private function jeApi(): bool
    {
        return $this->cestaBezJazyka === 'api' || str_starts_with($this->cestaBezJazyka, 'api/');
    }

    /**
     * @return array<string, mixed>
     */
    private function dotazBezPrepnuti(): array
    {
        $dotaz = $this->dotaz;
        unset($dotaz[self::PARAMETR_PREPNUTI]);

        return $dotaz;
    }
}
