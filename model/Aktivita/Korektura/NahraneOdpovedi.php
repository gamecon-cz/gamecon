<?php

declare(strict_types=1);

namespace Gamecon\Aktivita\Korektura;

/**
 * Answer files uploaded at once (`<input type="file" name="…[]" multiple>`): what came, by file name, and why
 * a file did not. Nothing is dropped silently, a missing answer would leave its activities waiting unnoticed.
 */
readonly class NahraneOdpovedi
{
    /**
     * @param array<string, string> $odpovediPodleSouboru
     * @param array<string>         $varovani
     */
    private function __construct(
        public array $odpovediPodleSouboru,
        public array $varovani,
    ) {
    }

    /**
     * @param array<string, mixed>|null     $soubory       the entry of $_FILES for the field, its keys hold lists
     * @param int                           $nejvysSouboru max_file_uploads: PHP leaves out the files over it, without an error
     * @param (\Closure(string): bool)|null $jeNahrany     is_uploaded_file(), a test passes its own
     */
    public static function zFormulare(?array $soubory, int $nejvysSouboru, ?\Closure $jeNahrany = null): self
    {
        $jeNahrany ??= is_uploaded_file(...);
        $nazvy = (array) ($soubory['name'] ?? []);
        $cesty = (array) ($soubory['tmp_name'] ?? []);
        $chyby = (array) ($soubory['error'] ?? []);

        $odpovedi = [];
        $varovani = [];
        foreach ($nazvy as $poradi => $nazev) {
            $chyba = (int) ($chyby[$poradi] ?? UPLOAD_ERR_NO_FILE);
            if ($chyba === UPLOAD_ERR_NO_FILE) {
                // the field sent without a file
                continue;
            }
            $nazev = (string) $nazev !== ''
                ? (string) $nazev
                : 'soubor ' . ((int) $poradi + 1);
            if ($chyba !== UPLOAD_ERR_OK) {
                $varovani[] = "{$nazev}: nenahrál se (" . self::duvod($chyba) . '), nahraj ho znovu.';
                continue;
            }
            $cesta = (string) ($cesty[$poradi] ?? '');
            $obsah = $cesta !== '' && $jeNahrany($cesta)
                ? file_get_contents($cesta)
                : false;
            if ($obsah === false) {
                $varovani[] = "{$nazev}: nenahrál se, nahraj ho znovu.";
                continue;
            }
            // two files of the same name (from two folders) are both kept
            $klic = $nazev;
            for ($dalsi = 2; isset($odpovedi[$klic]); ++$dalsi) {
                $klic = "{$nazev} ({$dalsi})";
            }
            $odpovedi[$klic] = $obsah;
        }
        if ($nejvysSouboru > 0 && count($nazvy) >= $nejvysSouboru) {
            $varovani[] = "Najednou jde nahrát nejvýš {$nejvysSouboru} souborů, další server zahodí. Jestli jsi jich vybral víc, nahraj zbytek zvlášť.";
        }

        return new self($odpovedi, $varovani);
    }

    private static function duvod(int $chyba): string
    {
        return match ($chyba) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'je moc velký',
            UPLOAD_ERR_PARTIAL => 'přenesl se jen zčásti',
            default            => "chyba serveru {$chyba}",
        };
    }
}
