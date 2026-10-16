<?php

declare(strict_types=1);

namespace Gamecon\Kanaly\EmailovaSablona;

/**
 * Red block above the page. Unlike a {@see \Chyba} message it does not vanish on its own,
 * because the problem lasts until somebody fixes the text.
 */
class ProblemySablonHtml
{
    /**
     * @param list<ProblemSablony> $problemy
     */
    public static function vykresli(array $problemy): string
    {
        if ($problemy === []) {
            return '';
        }

        $neodeslatelne = array_values(array_filter($problemy, static fn(ProblemSablony $problem) => $problem->zabranujeOdeslani));
        $nedosazene    = array_values(array_filter($problemy, static fn(ProblemSablony $problem) => !$problem->zabranujeOdeslani));

        $bloky = '';
        if ($neodeslatelne !== []) {
            $bloky .= self::blok(
                'E-mail se neodešle, protože jeho text chybí nebo je prázdný.',
                'Vyplň text:',
                $neodeslatelne,
            );
        }
        if ($nedosazene !== []) {
            $bloky .= self::blok(
                'Text e-mailu používá neveřejné nastavení nebo neznámou hodnotu, proto se do e-mailu nedosadí.',
                'E-mail se přesto odešle a symbol v něm zůstane tak, jak je napsaný. Neveřejné nastavení zveřejníš zaškrtnutím „Veřejné“ na stránce Nastavení, neznámý symbol odstraň z textu:',
                $nedosazene,
            );
        }

        return <<<HTML
<div class="chybaBlok inline chybaBlok-errorHlaska" id="problemySablon">
  <div class="hlaska errorHlaska" style="text-align: left">{$bloky}</div>
</div>
HTML;
    }

    /**
     * @param list<ProblemSablony> $problemy
     */
    private static function blok(string $nadpis, string $vyzva, array $problemy): string
    {
        $polozky = '';
        foreach ($problemy as $problem) {
            $odkaz = htmlspecialchars($problem->odkaz, ENT_QUOTES);
            $nazev = htmlspecialchars($problem->nazev, ENT_QUOTES);
            $chyby = implode(' ', array_map(
                static fn(string $chyba) => htmlspecialchars($chyba, ENT_QUOTES),
                $problem->chyby,
            ));
            $polozky .= "<li><a href=\"{$odkaz}\">{$nazev}</a>: {$chyby}</li>";
        }

        return <<<HTML
    <p style="margin: 0 0 .5em"><strong>{$nadpis}</strong> {$vyzva}</p>
    <ul style="margin: 0 0 .5em 1.5em">{$polozky}</ul>
HTML;
    }
}
