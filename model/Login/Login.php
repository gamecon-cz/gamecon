<?php declare(strict_types=1);

namespace Gamecon\Login;

class Login
{
    public function dejHtmlLogin(): string {
        $loginTemplate = new \XTemplate(__DIR__ . '/templates/login.xtpl');

        $loginTemplate->assign([
            'pageTitle' => 'GameCon – Administrace',
            'base' => URL_ADMIN . '/',
        ]);

        $chyba = \Chyba::vyzvedniChybu();
        if ($chyba) {
            $loginTemplate->assign('chyba', $chyba);
            $loginTemplate->parse('login.chyba');
        }

        $loginTemplate->parse('login');
        return $loginTemplate->text('login');
    }
}
