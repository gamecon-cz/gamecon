<?php declare(strict_types=1);

namespace Gamecon\Login;

class Login
{
    public function dejHtmlLogin(): string {

        $loginTemplate = $this->dejLoginTemplate();

        $chyba = \Chyba::vyzvedniChybu();
        if ($chyba) {
            $loginTemplate->assign('chyba', $chyba);
            $loginTemplate->parse('login.chyba');
        }

        $loginTemplate->parse('login');
        return $loginTemplate->text('login');
    }

    private function dejLoginTemplate(): \XTemplate {
        $loginTemplate = new \XTemplate(__DIR__ . '/templates/login.xtpl');

        $loginTemplate->assign([
            'pageTitle' => 'GameCon – Administrace',
            'base' => URL_ADMIN . '/',
        ]);

        $this->pridejLokalniAssety($loginTemplate);

        return $loginTemplate;
    }

    private function pridejLokalniAssety(\XTemplate $template) {
        static $localAssets = [
            'stylesheets' => [
                __DIR__ . '/../../admin/files/login/login.css',
            ],
            'javascripts' => [
                __DIR__ . '/../../admin/files/login/login.js',
            ],
        ];
        foreach ($localAssets['stylesheets'] as $stylesheet) {
            $template->assign('url', str_replace(__DIR__ . '/../../admin/', '', $stylesheet));
            $template->assign('version', md5_file($stylesheet));
            $template->parse('login.stylesheet');
        }
        foreach ($localAssets['javascripts'] as $javascript) {
            $template->assign('url', str_replace(__DIR__ . '/../../admin/', '', $javascript));
            $template->assign('version', md5_file($javascript));
            $template->parse('login.javascript');
        }
    }
}
