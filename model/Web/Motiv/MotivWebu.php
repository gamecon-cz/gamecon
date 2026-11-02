<?php

declare(strict_types=1);

namespace Gamecon\Web\Motiv;

use Gamecon\Web\VzhledWebuEnum;

/**
 * How the web is coloured for this request: the colours of the year, a special theme on top of them (only the modern
 * look has themes), or what a designer previews instead.
 */
readonly class MotivWebu
{
    public function __construct(
        public BarvyWebu $barvy,
        public ?MotivWebuEnum $motiv,
        /**
         * what is previewed, for the bar that says so; null when nothing is
         */
        public ?string $popisNahledu = null,
    ) {
    }

    /**
     * @param NahledMotivuWebu|null $nahled only for someone who may preview, see NahledMotivuWebu::smiNahlizet()
     */
    public static function zjisti(
        BarvyWebuRepository $barvy,
        NaplanovaneMotivyWebuRepository $motivy,
        \DateTimeInterface $kdy,
        ?NahledMotivuWebu $nahled,
    ): self {
        if ($nahled?->jeNavrhBarev()) {
            $navrh = $barvy->dej(StavBarevWebuEnum::NAVRH);

            // The preview of the colours shows them alone, a planned theme would cover them
            return $navrh !== null
                ? new self($navrh->barvy, null, 'návrh barev ročníku')
                : new self($barvy->zverejnene(), null, 'návrh barev ročníku, žádný ale není, takže vidíš zveřejněné barvy');
        }
        $nahlizenyMotiv = $nahled?->getMotiv();
        if ($nahlizenyMotiv !== null) {
            return new self($barvy->zverejnene(), $nahlizenyMotiv, 'motiv ' . $nahlizenyMotiv->getNazev());
        }

        return new self($barvy->zverejnene(), $motivy->zobrazovany($kdy)?->motiv);
    }

    /**
     * Into <head> after the styles of the look, whose variables it overrides. The classic look gets the colours only,
     * a theme is written for the modern one.
     *
     * @param string $adresarWebu where the stylesheet of the theme is, for its version
     */
    public function getHtmlDoHlavicky(string $adresarWebu, VzhledWebuEnum $vzhled): string
    {
        $html = '';
        $css = $this->barvy->getCss();
        if ($css !== '') {
            $html .= '<style>' . $css . '</style>';
        }
        if ($this->motiv !== null && $vzhled->isModerni()) {
            $soubor = $this->motiv->getCssSoubor();
            $cesta = $adresarWebu . '/' . $soubor;
            $verze = is_file($cesta)
                ? md5_file($cesta)
                : '';
            $html .= '<link rel="stylesheet" href="' . htmlspecialchars($soubor . '?version=' . $verze) . '">';
        }

        return $html;
    }

    /**
     * For <meta name="theme-color">, the colour of the browser bar on a phone, the same as the header.
     */
    public function getBarvaProhlizece(): string
    {
        return $this->barvy->getHodnota(BarvaWebuEnum::INKOUST);
    }
}
