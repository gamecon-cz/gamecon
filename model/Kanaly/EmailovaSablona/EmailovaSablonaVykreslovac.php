<?php

declare(strict_types=1);

namespace Gamecon\Kanaly\EmailovaSablona;

use Gamecon\Kanaly\EmailovaSablona\Exceptions\ChybiPromennaSablony;

/**
 * Turns a saved template and variable values into the subject and text to send. A symbol that cannot be substituted
 * (a non-public or unknown setting, a typo) stays as written: the e-mail goes out and it is visible what is missing.
 */
class EmailovaSablonaVykreslovac
{
    public function __construct(
        private readonly SystemoveHodnotyProSablony $systemoveHodnoty,
    ) {
    }

    /**
     * @param array<string, string|int|float|\Stringable> $promenne exactly those the definition declares
     */
    public function vykresli(
        EmailovaSablonaDefinice $definice,
        string                  $predmet,
        string                  $text,
        array                   $promenne,
    ): VykreslenaSablona {
        $this->zkontrolujPromenne($definice, $promenne);

        return new VykreslenaSablona(
            predmet: $this->dosad($definice, $predmet, $promenne, jePredmet: true),
            text: $this->dosad($definice, $text, $promenne, jePredmet: false),
            format: $definice->format,
        );
    }

    /**
     * Subject only, for when the message is not being built yet and no values for the text are available.
     *
     * @param array<string, string|int|float|\Stringable> $promenne exactly those the subject may contain
     */
    public function vykresliPredmet(
        EmailovaSablonaDefinice $definice,
        string                  $predmet,
        array                   $promenne = [],
    ): string {
        $this->zkontrolujPromenne($definice, $promenne, array_keys($definice->promenneVPredmetu()));

        return $this->dosad($definice, $predmet, $promenne, jePredmet: true);
    }

    /**
     * @param array<string, string|int|float|\Stringable> $promenne
     * @param list<string>|null $ocekavane by default all declared variables
     */
    private function zkontrolujPromenne(
        EmailovaSablonaDefinice $definice,
        array                   $promenne,
        ?array                  $ocekavane = null,
    ): void {
        $ocekavane ??= array_keys($definice->promenne);
        $chybejici = array_diff($ocekavane, array_keys($promenne));
        $navic     = array_diff(array_keys($promenne), $ocekavane);
        if ($chybejici === [] && $navic === []) {
            return;
        }

        throw new ChybiPromennaSablony(sprintf(
            "Šablona '%s' dostala jiné proměnné, než deklaruje. Chybí: [%s], navíc: [%s].",
            $definice->kod->value,
            implode(', ', $chybejici),
            implode(', ', $navic),
        ));
    }

    /**
     * @param array<string, string|int|float|\Stringable> $promenne
     */
    private function dosad(
        EmailovaSablonaDefinice $definice,
        string                  $obsah,
        array                   $promenne,
        bool                    $jePredmet,
    ): string {
        $jeHtml   = $definice->jeHtml() && !$jePredmet;
        $vysledek = '';
        foreach (RozkladSymbolu::rozloz($obsah) as $token) {
            $vysledek .= match ($token->typ) {
                TypTokenuEnum::TEXT               => $jeHtml
                    ? str_replace(["\r\n", "\n"], '<br />', $token->hodnota)
                    : $token->hodnota,
                TypTokenuEnum::SYMBOL             => $this->hodnotaSymbolu($definice, $token, $promenne, $jePredmet, $jeHtml),
                TypTokenuEnum::NEUZAVRENY_SYMBOL,
                TypTokenuEnum::CISLICNY_SYMBOL    => $this->nechej($token, $jeHtml),
            };
        }

        return $vysledek;
    }

    /**
     * @param array<string, string|int|float|\Stringable> $promenne
     */
    private function hodnotaSymbolu(
        EmailovaSablonaDefinice $definice,
        Token                   $symbol,
        array                   $promenne,
        bool                    $jePredmet,
        bool                    $jeHtml,
    ): string {
        if (RozkladSymbolu::jePromenna($symbol->hodnota)) {
            $promenna = $definice->promenna($symbol->hodnota);
            if ($promenna === null || ($jePredmet && !$promenna->vPredmetu)) {
                return $this->nechej($symbol, $jeHtml);
            }
            $hodnota = (string)$promenne[$symbol->hodnota];

            return $jeHtml && !$promenna->html
                ? $this->escapuj($hodnota)
                : $hodnota;
        }

        if (RozkladSymbolu::jeSystemovaHodnota($symbol->hodnota)) {
            $hodnota = $this->systemoveHodnoty->hodnota($symbol->hodnota);
            if ($hodnota === null) {
                return $this->nechej($symbol, $jeHtml);
            }

            return $jeHtml
                ? $this->escapuj($hodnota)
                : $hodnota;
        }

        return $this->nechej($symbol, $jeHtml);
    }

    private function nechej(Token $symbol, bool $jeHtml): string
    {
        $zapsano = $symbol->jakoZapsano();

        return $jeHtml
            ? $this->escapuj($zapsano)
            : $zapsano;
    }

    /**
     * Without double encoding: an activity name that already has `&amp;` in the database must not show up as '&amp;amp;'.
     */
    private function escapuj(string $hodnota): string
    {
        return htmlspecialchars($hodnota, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}
