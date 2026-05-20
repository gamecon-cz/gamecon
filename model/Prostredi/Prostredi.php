<?php

namespace Gamecon\Prostredi;

/**
 * Identifies the deployment environment.
 *
 * Single source of truth for "are we running on ostra / beta / preview /
 * locale" — replaces the older parallel triple of jsmeNaBete + jsmeNaLocale
 * + jsmeNaPreview booleans (in SystemoveNastaveni and Web\Info) plus the
 * scattered emoji/label literals in templates and prefix-returning methods.
 *
 * The four cases are exhaustive: any host that the app boots on matches
 * exactly one. The string backing values are what gets written to the
 * generated nastaveni-<env>.php file and used as IDs in any future
 * env-aware logging or metrics.
 */
enum Prostredi: string
{
    case Production = 'ostre';
    case Beta       = 'beta';
    case Preview = 'preview';
    case Locale  = 'locale';

    /**
     * Short symbol for badges and prefixes. Empty for ostre — production
     * shouldn't be visually flagged.
     */
    public function prefix(): string
    {
        return match ($this) {
            self::Production => '',
            self::Beta       => 'β',
            self::Preview    => '🧐',
            self::Locale     => 'άλφα',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Production => '',
            self::Beta       => 'beta',
            self::Preview    => 'preview',
            self::Locale     => 'local',
        };
    }
    public function ribbonLabel(): string
    {
        return $this->prefix() . ' ' . $this->label();
    }

    /**
     * Path to the `verejne-nastaveni-<env>.php` that drives this env's
     * runtime config (DB credentials, URL_*, ANALYTICS, etc.). `null` for
     * Locale because that env has the bespoke `nastaveni-local.php` +
     * `nastaveni-local-default.php` pair; see zavadec-nastaveni.php for
     * how it's loaded.
     */
    public function souborVerejnehoNastaveni(): ?string
    {
        $base = __DIR__ . '/../../nastaveni/';

        return match ($this) {
            self::Production => $base . 'verejne-nastaveni-produkce.php',
            self::Beta       => $base . 'verejne-nastaveni-beta.php',
            self::Preview    => $base . 'verejne-nastaveni-preview.php',
            self::Locale     => null,
        };
    }

    /**
     * Detects the active environment from the current host. Delegates to
     * the free `jsmeNa*` functions in model/funkce/funkce.php (which read
     * SERVER_NAME / $_ENV['ENV']) so the legacy callsites of those
     * functions and this enum agree by construction.
     *
     * Order matters: a host can match both jsmeNaLocale (via $_ENV) and a
     * regex-based check; locale wins to preserve the prior priority. The
     * fallback to Ostre is intentional — when SERVER_NAME hasn't been
     * defined yet, callers should already have errored on a missing
     * env file.
     */
    public static function detect(): self
    {
        if (\jsmeNaLocale()) {
            return self::Locale;
        }
        if (\jsmeNaBete()) {
            return self::Beta;
        }
        if (\jsmeNaPreview()) {
            return self::Preview;
        }
        return self::Production;
    }
}
