<?php

declare(strict_types=1);

namespace Gamecon\Shop\Cip;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Web\Info;
use Gamecon\XTemplate\XTemplate;

class CipHtml
{
    private ?XTemplate $cipTemplate = null;

    public function __construct(
        private readonly string             $jsVyjimkovac,
        private readonly SystemoveNastaveni $systemoveNastaveni,
    )
    {
    }

    public function dejHtmlCipu(\Uzivatel $ucastnik): string
    {
        $template = $this->dejCipTemplate();

        $template->assign('jsVyjimkovac', $this->jsVyjimkovac);

        $this->pridejLokalniAssety($template);

        $urlZpet = localBackUrl(URL_ADMIN);
        $template->assign('urlZpet', $urlZpet);
        $template->assign('ucastnikJmenoNick', $ucastnik->jmenoNick());

        $template->parse('cip');
        return $template->text('cip');
    }

    private function dejCipTemplate(): XTemplate
    {
        if ($this->cipTemplate === null) {
            $this->cipTemplate = new XTemplate(__DIR__ . '/templates/cip.xtpl');
            $this->cipTemplate->assign(
                'title',
                (new Info($this->systemoveNastaveni))->pridejPrefixPodleVyvoje('Čip'),
            );
        }
        return $this->cipTemplate;
    }

    private function pridejLokalniAssety(XTemplate $template)
    {
        static $localAssets = [
            'stylesheets' => [
            ],
            'javascripts' => [
                'text'   => [
                ],
                /*
                 * Pozor, JS moduly se automaticky načítají jako deffer, tedy asynchronně a vykonávají se až někdy po načtení celé stránky.
                 * Zároveň kód načtený jako module nejde volat z HTML.
                 */
                'module' => [
                ],
            ],
        ];
        foreach ($localAssets['stylesheets'] as $stylesheet) {
            $template->assign('url', str_replace(__DIR__ . '/../../../admin/', '', $stylesheet));
            $template->assign('version', md5_file($stylesheet));
            $template->parse('cip.stylesheet');
        }
        foreach ($localAssets['javascripts'] as $type => $javascripts) {
            foreach ($javascripts as $javascript) {
                $template->assign('url', str_replace(__DIR__ . '/../../../admin/', '', $javascript));
                $template->assign('version', md5_file($javascript));
                if ($type === 'module') {
                    $template->parse('cip.javascript.module');
                } else {
                    $template->parse('cip.javascript.text');
                }
            }
            $template->parse('cip.javascript');
        }
    }

}
