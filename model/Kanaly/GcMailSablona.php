<?php

declare(strict_types=1);

namespace Gamecon\Kanaly;

/**
 * Obalí obsah mailu do brandované HTML šablony ve stylu gamecon.cz
 * (červené záhlaví s logem, světlé tělo, patička).
 *
 * E-mailové klienty mají notoricky děravou podporu CSS — proto je layout
 * postavený na tabulkách a inline stylech (žádný {@code <style>} blok ani
 * externí CSS, ty Gmail i Outlook ořezávají) a logo je vykreslené jako
 * textový wordmark, ne SVG (to e-mailové klienty nerenderují).
 */
class GcMailSablona
{
    private const CERVENA = '#E22630';
    private const TMAVA = '#10111A';
    private const KREMOVA = '#F6F1EA';
    private const PISMO = "'Gilroy', 'Segoe UI', Arial, sans-serif";

    /**
     * @param string $obsahHtml vnitřní obsah mailu (už jako HTML, např. z hlaskaMail())
     */
    public static function obal(string $obsahHtml, string $nadpis = ''): string
    {
        $cervena = self::CERVENA;
        $tmava = self::TMAVA;
        $kremova = self::KREMOVA;
        $pismo = self::PISMO;
        $rok = date('Y');

        $nadpisHtml = $nadpis === ''
            ? ''
            : '<h1 style="margin: 0 0 24px; font-size: 26px; line-height: 1.2; font-weight: 800; color: ' . $tmava . ';">'
                . htmlspecialchars($nadpis, ENT_QUOTES) . '</h1>';

        return <<<HTML
            <!DOCTYPE html>
            <html lang="cs">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="x-apple-disable-message-reformatting">
            </head>
            <body style="margin: 0; padding: 0; background-color: {$kremova}; font-family: {$pismo}; color: {$tmava};">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: {$kremova};">
                    <tr>
                        <td align="center" style="padding: 24px 12px;">
                            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width: 600px; max-width: 100%; background-color: #ffffff; border-radius: 12px; overflow: hidden;">
                                <tr>
                                    <td style="background-color: {$cervena}; padding: 28px 40px;">
                                        <span style="font-size: 24px; font-weight: 800; letter-spacing: 0.02em; color: #ffffff;">GameCon</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 40px; font-size: 16px; line-height: 1.6; color: {$tmava};">
                                        {$nadpisHtml}
                                        {$obsahHtml}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="background-color: {$cervena}; padding: 24px 40px; font-size: 13px; line-height: 1.5; color: #ffffff;">
                                        <a href="https://gamecon.cz/" style="color: #ffffff; font-weight: bold; text-decoration: underline;">gamecon.cz</a>
                                        <br>
                                        <span style="opacity: 0.7;">&copy; {$rok} GameCon, z. s.</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
            HTML;
    }
}
