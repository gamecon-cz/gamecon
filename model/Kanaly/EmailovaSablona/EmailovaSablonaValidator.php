<?php

declare(strict_types=1);

namespace Gamecon\Kanaly\EmailovaSablona;

/**
 * Rules for saving a template. The editor's JavaScript only mirrors them for convenience,
 * and this always decides.
 */
class EmailovaSablonaValidator
{
    public function __construct(
        private readonly SystemoveHodnotyProSablony $systemoveHodnoty,
    ) {
    }

    /**
     * @return list<string> errors in Czech, an empty array means the template can be saved
     */
    public function validuj(
        EmailovaSablonaDefinice $definice,
        string                  $predmet,
        string                  $text,
    ): array {
        $chyby = [];

        if (RozkladSymbolu::jePrazdny($predmet)) {
            $chyby[] = 'Předmět nesmí být prázdný.';
        } elseif (preg_match('~[\r\n]~', trim($predmet))) {
            $chyby[] = 'Předmět musí být na jednom řádku.';
        }
        if (RozkladSymbolu::jePrazdny($text)) {
            $chyby[] = 'Text nesmí být prázdný.';
        }

        $chyby = [
            ...$chyby,
            ...$this->chybySymbolu('Předmět', $predmet, $definice, jePredmet: true),
            ...$this->chybySymbolu('Text', $text, $definice, jePredmet: false),
        ];

        return array_values(array_unique($chyby));
    }

    /**
     * Only problems with UPPERCASE values. Used to re-check already saved
     * templates in which something may have stopped being public meanwhile.
     *
     * @return list<string>
     */
    public function chybySystemovychHodnot(string $obsah): array
    {
        $chyby = [];
        foreach (RozkladSymbolu::rozloz($obsah) as $token) {
            if ($token->typ !== TypTokenuEnum::SYMBOL || !RozkladSymbolu::jeSystemovaHodnota($token->hodnota)) {
                continue;
            }
            $chyba = $this->chybaSystemoveHodnoty($token);
            if ($chyba !== null) {
                $chyby[] = $chyba;
            }
        }

        return array_values(array_unique($chyby));
    }

    /**
     * @return list<string>
     */
    private function chybySymbolu(
        string                  $misto,
        string                  $obsah,
        EmailovaSablonaDefinice $definice,
        bool                    $jePredmet,
    ): array {
        $chyby = [];
        foreach (RozkladSymbolu::rozloz($obsah) as $token) {
            $chyba = match ($token->typ) {
                TypTokenuEnum::TEXT               => null,
                TypTokenuEnum::NEUZAVRENY_SYMBOL  => "Symbol „{$token->jakoZapsano()}“ není zavřený, chybí mu závěrečné procento. Samotné procento napiš jako %%.",
                TypTokenuEnum::CISLICNY_SYMBOL    => "„{$token->jakoZapsano()}“ není symbol, symboly mají jména. Samotné procento napiš jako %%.",
                TypTokenuEnum::SYMBOL             => $this->chybaSymbolu($token, $definice, $jePredmet),
            };
            if ($chyba !== null) {
                $chyby[] = "$misto: $chyba";
            }
        }

        if (preg_match_all('~\{[A-Za-z][A-Za-z0-9_]*\}~', $obsah, $slozene)) {
            foreach ($slozene[0] as $slozenyZapis) {
                $chyby[] = "{$misto}: symboly se píšou mezi procenta, ne do složených závorek („{$slozenyZapis}“).";
            }
        }

        return $chyby;
    }

    private function chybaSymbolu(
        Token                   $symbol,
        EmailovaSablonaDefinice $definice,
        bool                    $jePredmet,
    ): ?string {
        $zapsano = $symbol->jakoZapsano();

        if (RozkladSymbolu::jePromenna($symbol->hodnota)) {
            $promenna = $definice->promenna($symbol->hodnota);
            if ($promenna === null) {
                return "„{$zapsano}“ není v téhle šabloně k dispozici.";
            }
            if ($jePredmet && !$promenna->vPredmetu) {
                return "„{$zapsano}“ nejde použít v předmětu, jen v textu.";
            }

            return null;
        }

        if (RozkladSymbolu::jeSystemovaHodnota($symbol->hodnota)) {
            return $this->chybaSystemoveHodnoty($symbol);
        }

        return "„{$zapsano}“ má špatný tvar: proměnné se píšou malými písmeny, systémové hodnoty VELKÝMI.";
    }

    private function chybaSystemoveHodnoty(Token $symbol): ?string
    {
        $zapsano = $symbol->jakoZapsano();

        return match ($this->systemoveHodnoty->stav($symbol->hodnota)) {
            StavSystemoveHodnotyEnum::POVOLENA  => null,
            StavSystemoveHodnotyEnum::NEVEREJNA => "„{$zapsano}“ je neveřejné nastavení a do e-mailu se nedosadí. Zveřejnit ho jde na stránce Nastavení.",
            StavSystemoveHodnotyEnum::NEZNAMA   => "„{$zapsano}“ není známá systémová hodnota.",
        };
    }
}
