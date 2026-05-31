<?php

declare(strict_types=1);

namespace Gamecon\Kanaly;

/**
 * Vytáhne čitelné tělo (HTML, případně text) ze syrové MIME zprávy,
 * jakou ukládá {@see MailLogger} do sloupce `telo` (výstup Symfony
 * Email::toString()). Slouží jako fallback pro starší záznamy, které
 * nemají samostatně uložený dekódovaný HTML obsah (`telo_html`).
 */
final class MimeNahled
{
    /**
     * @return array{html: ?string, text: ?string} dekódované části;
     *                                             `null`, pokud daná část v MIME zprávě není
     */
    public static function vytahniTela(string $mime): array
    {
        [$hlavicky, $telo] = self::rozdelHlavickyATelo($mime);

        $contentType = $hlavicky['content-type'] ?? '';
        $boundary = self::boundary($contentType);

        if ($boundary === null) {
            // jednoduchá (ne-multipart) zpráva
            $obsah = self::dekodujTelo($telo, $hlavicky['content-transfer-encoding'] ?? '', $contentType);
            $jeHtml = stripos($contentType, 'text/html') !== false;

            return [
                'html' => $jeHtml ? $obsah : null,
                'text' => $jeHtml ? null : $obsah,
            ];
        }

        $html = null;
        $text = null;
        foreach (self::rozdelNaCasti($telo, $boundary) as $cast) {
            [$castHlavicky, $castTelo] = self::rozdelHlavickyATelo($cast);
            $castContentType = $castHlavicky['content-type'] ?? '';

            // vnořený multipart (např. multipart/related kolem HTML) – rekurze
            if (self::boundary($castContentType) !== null) {
                $vnoreno = self::vytahniTela($cast);
                $html ??= $vnoreno['html'];
                $text ??= $vnoreno['text'];
                continue;
            }

            $obsah = self::dekodujTelo(
                $castTelo,
                $castHlavicky['content-transfer-encoding'] ?? '',
                $castContentType,
            );
            if (stripos($castContentType, 'text/html') !== false) {
                $html ??= $obsah;
            } elseif (stripos($castContentType, 'text/plain') !== false) {
                $text ??= $obsah;
            }
        }

        return [
            'html' => $html,
            'text' => $text,
        ];
    }

    /**
     * @return array{0: array<string, string>, 1: string} [hlavičky (klíče lowercase), tělo]
     */
    private static function rozdelHlavickyATelo(string $mime): array
    {
        $mime = str_replace("\r\n", "\n", $mime);
        $delic = strpos($mime, "\n\n");
        if ($delic === false) {
            return [[], $mime];
        }

        $hlavickovaCast = substr($mime, 0, $delic);
        $telo = substr($mime, $delic + 2);

        // rozbalení skládaných (folded) hlaviček – pokračovací řádek začíná mezerou/tabem
        $hlavickovaCast = preg_replace('/\n[ \t]+/', ' ', $hlavickovaCast) ?? $hlavickovaCast;

        $hlavicky = [];
        foreach (explode("\n", $hlavickovaCast) as $radek) {
            $dvojtecka = strpos($radek, ':');
            if ($dvojtecka === false) {
                continue;
            }
            $nazev = strtolower(trim(substr($radek, 0, $dvojtecka)));
            $hlavicky[$nazev] = trim(substr($radek, $dvojtecka + 1));
        }

        return [$hlavicky, $telo];
    }

    private static function boundary(string $contentType): ?string
    {
        if (stripos($contentType, 'multipart/') === false) {
            return null;
        }
        if (preg_match('/boundary="?([^";]+)"?/i', $contentType, $shoda) !== 1) {
            return null;
        }

        return $shoda[1];
    }

    /**
     * @return list<string>
     */
    private static function rozdelNaCasti(string $telo, string $boundary): array
    {
        $casti = explode('--' . $boundary, $telo);
        // první prvek je preambule, poslední uzávěr ("--boundary--") + epilog
        array_shift($casti);
        $vysledek = [];
        foreach ($casti as $cast) {
            // uzavírací delimiter "--" hned za boundary ⇒ konec
            if (str_starts_with($cast, '--')) {
                break;
            }
            $vysledek[] = ltrim($cast, "\n");
        }

        return $vysledek;
    }

    private static function dekodujTelo(string $telo, string $kodovani, string $contentType): string
    {
        $kodovani = strtolower(trim($kodovani));
        $obsah = match ($kodovani) {
            'quoted-printable' => quoted_printable_decode($telo),
            'base64'           => (string) base64_decode(trim($telo), true),
            default            => $telo,
        };

        $charset = self::charset($contentType);
        if ($charset !== null && strtolower($charset) !== 'utf-8') {
            $prevedeno = @mb_convert_encoding($obsah, 'UTF-8', $charset);
            if ($prevedeno !== false) {
                $obsah = $prevedeno;
            }
        }

        return rtrim($obsah, "\n");
    }

    private static function charset(string $contentType): ?string
    {
        if (preg_match('/charset="?([^";]+)"?/i', $contentType, $shoda) !== 1) {
            return null;
        }

        return $shoda[1];
    }
}
