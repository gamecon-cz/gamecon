<?php

declare(strict_types=1);

namespace Gamecon\Web;

use App\Entity\User;
use App\Service\JwtService;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * Kořen Preact komponenty na veřejném webu i s tím, co potřebuje: bundle, styl a JWT
 * přihlášeného uživatele pro volání Symfony API. Používá ho přihláška i widget jídelníčku.
 */
class PreactSekce
{
    private static bool $sdilenaAktivaVlozena = false;

    public static function html(string $idKorene, \Uzivatel $u, SystemoveNastaveni $systemoveNastaveni): string
    {
        $jwtService = $systemoveNastaveni->getService(JwtService::class);
        // Token se razí z User entity, ne z legacy Uzivatele: jmenoNick() vrací jméno s přezdívkou
        // („Jakub „Elden“ Jandák“), kdežto entita samotné jméno — a to je to, co GUI zobrazuje.
        $userEntity = $systemoveNastaveni->getService('doctrine.orm.entity_manager')->find(User::class, $u->id());
        if ($userEntity === null) {
            throw new \RuntimeException(sprintf('Uživatel %d nemá protějšek v nové vrstvě.', $u->id()));
        }
        $jwt = $jwtService->generateJwtToken($jwtService->extractUserData($userEntity));

        // URL_WEBU = http://localhost:85/web — strip /web to get site root for Symfony API
        $siteRoot       = preg_replace('#/web$#', '', URL_WEBU);
        $symfonyApiBase = $siteRoot . '/symfony/api/';
        $bundleUrl      = URL_WEBU . '/soubory/ui/bundle.js';
        $styleUrl       = URL_WEBU . '/soubory/ui/style.css';

        // Bundle i styl jsou pro všechny sekce společné; podruhé už je nevkládáme, jinak by se
        // Preact načetl a namountoval dvakrát.
        $sdilenaAktiva = '';
        if (!self::$sdilenaAktivaVlozena) {
            self::$sdilenaAktivaVlozena = true;
            // Do JS kontextu vždy přes json_encode, ať formát tokenu nebo URL nemůže rozbít skript.
            $jwtJs            = json_encode($jwt, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $symfonyApiBaseJs = json_encode($symfonyApiBase, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $sdilenaAktiva    = <<<HTML
                <link rel="stylesheet" href="{$styleUrl}">
                <script>
                    window.GAMECON_KONSTANTY = window.GAMECON_KONSTANTY || {};
                    window.GAMECON_KONSTANTY.JWT = {$jwtJs};
                    window.GAMECON_KONSTANTY.BASE_PATH_SYMFONY_API = {$symfonyApiBaseJs};
                </script>
                <script type="module" src="{$bundleUrl}"></script>
            HTML;
        }

        return <<<HTML
            <div id="{$idKorene}"></div>
            {$sdilenaAktiva}
        HTML;
    }
}
