<?php

declare(strict_types=1);

namespace Gamecon\Aktivita\Korektura;

/**
 * Reads the AI's answer to a file made by {@see SouborKorektur}. Forgiving on purpose: text around the
 * blocks (a chat likes to add some, or code fences) is ignored, a broken block is skipped with a warning
 * and the rest is read, so a cut-off answer still gives what came before the cut.
 */
readonly class OdpovedAi
{
    /**
     * @param array<int, NavrhZOdpovedi> $navrhy   by activity ID
     * @param array<string>              $varovani
     */
    public function __construct(
        public array $navrhy,
        public array $varovani,
    ) {
    }

    public static function rozparsuj(string $odpoved): self
    {
        $odpoved = str_replace("\r\n", "\n", $odpoved);
        $aktivita = SouborKorektur::ZNACKA_AKTIVITA;

        preg_match_all(
            '~<' . $aktivita . '\s+id\s*=\s*["\']?(?<id>\d+)["\']?\s*(?<prazdny>/)?>~i',
            $odpoved,
            $otevreni,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );
        if ($otevreni === []) {
            return new self([], ["V odpovědi není žádný blok <{$aktivita} id=\"…\">."]);
        }

        $navrhy = [];
        $varovani = [];
        foreach ($otevreni as $poradi => $otevreniBloku) {
            $idAktivity = (int) $otevreniBloku['id'][0];
            $zacatek = $otevreniBloku[0][1] + strlen($otevreniBloku[0][0]);
            $dalsi = $otevreni[$poradi + 1][0][1] ?? strlen($odpoved);

            if (($otevreniBloku['prazdny'][0] ?? '') === '/') {
                $obsah = '';
            } else {
                $konec = stripos($odpoved, "</{$aktivita}>", $zacatek);
                if ($konec === false || $konec > $dalsi) {
                    $varovani[] = "Aktivita {$idAktivity}: blok v odpovědi nemá konec (odpověď je asi useknutá), přeskakuji ji.";
                    continue;
                }
                $obsah = substr($odpoved, $zacatek, $konec - $zacatek);
            }

            if (isset($navrhy[$idAktivity])) {
                $varovani[] = "Aktivita {$idAktivity} je v odpovědi víckrát, beru poslední výskyt.";
            }

            // an AI may send the earlier version back although it should not, it is no proposal
            $obsah = preg_replace(
                '~<' . SouborKorektur::ZNACKA_DRIVEJSI . '\b.*?</' . SouborKorektur::ZNACKA_DRIVEJSI . '>~is',
                '',
                $obsah,
            );

            $chybneZnacky = [];
            $navrhy[$idAktivity] = new NavrhZOdpovedi(
                idAktivity: $idAktivity,
                nazev: self::pole($obsah, SouborKorektur::ZNACKA_NAZEV, $chybneZnacky),
                popisKratky: self::pole($obsah, SouborKorektur::ZNACKA_POPIS_KRATKY, $chybneZnacky),
                popis: self::pole($obsah, SouborKorektur::ZNACKA_POPIS, $chybneZnacky),
                zmeny: self::pole($obsah, SouborKorektur::ZNACKA_ZMENY, $chybneZnacky),
            );
            foreach ($chybneZnacky as $znacka) {
                $varovani[] = "Aktivita {$idAktivity}: značka <{$znacka}> nemá konec, ponechávám původní text.";
            }
        }

        return new self($navrhy, $varovani);
    }

    /**
     * @param array<string> $chybneZnacky
     */
    private static function pole(string $obsah, string $znacka, array &$chybneZnacky): ?string
    {
        if (preg_match('~<' . preg_quote($znacka, '~') . '>(.*?)</' . preg_quote($znacka, '~') . '>~is', $obsah, $shoda)) {
            return $shoda[1];
        }
        // `<gc-popis>` must not be found inside `<gc-popis-kratky>`, hence the closing `>` in the test
        if (stripos($obsah, "<{$znacka}>") !== false) {
            $chybneZnacky[] = $znacka;
        }

        return null;
    }
}
