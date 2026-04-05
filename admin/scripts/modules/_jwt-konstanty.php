<?php

/**
 * Generates JWT and BASE_PATH_SYMFONY_API for Preact's GAMECON_KONSTANTY.
 * Returns JS object properties string or empty string on failure.
 *
 * @param Uzivatel $u
 * @param \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 * @return string JS properties like: JWT: "...", BASE_PATH_SYMFONY_API: "...",
 */
function jwtKonstantyJs(
    Uzivatel $u,
    \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni,
): string {
    try {
        $kernel = $systemoveNastaveni->kernel();
        $container = $kernel->getContainer();
        /** @var \App\Service\JwtService $jwtService */
        $jwtService = $container->get(\App\Service\JwtService::class);
        $userEntity = $container->get('doctrine.orm.entity_manager')->find(\App\Entity\User::class, $u->id());
        if ($userEntity === null) {
            return '';
        }
        $jwt = $jwtService->generateJwtToken($jwtService->extractUserData($userEntity));
        $siteRoot = preg_replace('#/(admin|web)$#', '', URL_ADMIN);

        return "JWT: \"{$jwt}\",\n        BASE_PATH_SYMFONY_API: \"{$siteRoot}/symfony/api/\",";
    } catch (\Throwable) {
        return '';
    }
}
