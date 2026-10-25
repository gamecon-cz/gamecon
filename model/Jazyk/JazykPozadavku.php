<?php

declare(strict_types=1);

namespace Gamecon\Jazyk;

/**
 * Jazyk požadavku na veřejný web podle prefixu cesty: `/en/program` je anglicky, `/program` česky.
 *
 * Volbu si web pamatuje v cookie a u přihlášeného i v `uzivatele_hodnoty.jazyk`
 * (z toho čtou jazyk e-maily, které jdou mimo request). Kdo si zvolil angličtinu
 * a přijde na českou adresu (odkaz z e-mailu, natvrdo psaný odkaz), přesměruje se
 * na anglickou. Zpátky do češtiny vede přepínač přes `?jazyk=cs`.
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
     * Kam přesměrovat, aby návštěvník zůstal v jazyce, který si zvolil, nebo null, když se zůstává.
     *
     * @param Jazyk|null $zvoleny volba z cookie, případně uložená u přihlášeného uživatele
     */
    public function presmerovani(?Jazyk $zvoleny, string $metoda, bool $jeAjax): ?string
    {
        if (strtoupper($metoda) !== 'GET' || $jeAjax || $this->jeApi()) {
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
     * Jazyk, který si má web zapamatovat jako volbu návštěvníka, nebo null, když se volba nemění.
     * Česká adresa bez přepínače nic nemění: mohla přijít z natvrdo psaného odkazu.
     */
    public function jazykKZapamatovani(): ?Jazyk
    {
        return $this->jazykPrepnuti()
            ?? ($this->maPrefix
                ? $this->jazyk
                : null);
    }

    /**
     * Vyřídí přesměrování a zapamatování volby. Volá se na začátku web/index.php.
     */
    public function zpracuj(?\Uzivatel $uzivatel): void
    {
        $zapamatovat = $this->jazykKZapamatovani();
        if ($zapamatovat !== null) {
            if (($_COOKIE[self::COOKIE] ?? null) !== $zapamatovat->value) {
                setcookie(self::COOKIE, $zapamatovat->value, [
                    'expires'  => time() + 365 * 24 * 3600,
                    'path'     => '/',
                    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
                $_COOKIE[self::COOKIE] = $zapamatovat->value;
            }
            if ($uzivatel && $uzivatel->jazyk() !== $zapamatovat) {
                $uzivatel->nastavJazyk($zapamatovat);
            }
        }

        $zvoleny = Jazyk::tryFrom((string)($_COOKIE[self::COOKIE] ?? ''))
            ?? $uzivatel?->jazyk();
        $presmerovani = $this->presmerovani(
            $zvoleny,
            (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            is_ajax(),
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
