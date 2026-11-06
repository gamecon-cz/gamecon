<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Jak se soubor ukazuje ve výpisu: stránka Soubory i panel pod editorem stránky.
 */
final class VypisSouboru
{
    /**
     * Například „PDF, 37 kB“.
     *
     * @param string $soubor cesta nebo název, podle jeho přípony se pozná typ
     */
    public static function getUdaje(string $soubor, string $absolutniCesta): string
    {
        return implode(', ', array_filter([
            NahravaniObrazku::nazevFormatuPodlePripony($soubor) ?? mb_strtoupper(pathinfo($soubor, PATHINFO_EXTENSION)),
            NahravaniObrazku::lidskaVelikost((int) @filesize($absolutniCesta)),
        ], static fn (string $cast): bool => $cast !== ''));
    }

    /**
     * Čas souboru na disku, tedy nahrání nebo poslední nahrazení.
     */
    public static function getNahrano(string $absolutniCesta): string
    {
        return date('j. n. Y H:i', (int) @filemtime($absolutniCesta));
    }

    /**
     * Soubory ze stejné chvíle zůstanou v pořadí podle názvu, `usort` je stabilní.
     *
     * @param list<string> $soubory cesty vůči kořeni služby
     * @return list<string>
     */
    public static function odNejnovejsiho(NahravaniObrazku $nahravani, array $soubory): array
    {
        $casy = [];
        foreach ($soubory as $soubor) {
            $casy[$soubor] = (int) @filemtime($nahravani->cestaKSouboru($soubor));
        }
        usort($soubory, static fn (string $prvni, string $druhy): int => $casy[$druhy] <=> $casy[$prvni]);

        return $soubory;
    }
}
