<?php

declare(strict_types=1);

namespace Gamecon\Web\OznameniNaWebu;

use Gamecon\Web\VzhledWebuEnum;
use Gamecon\XTemplate\XTemplate;

/**
 * The bar with announcements under the menu of the public web, each look has its own template (sablony/<look>/oznameni.xtpl).
 */
class OznameniNaWebuHtml
{
    /**
     * @param list<OznameniNaWebu> $oznameni already without those the visitor closed
     * @param string               $cesta    the URL of the current page, closing an announcement comes back to it
     * @param array<string, mixed> $query    the query of the current page, without the 'req' added by the rewrite
     */
    public static function html(array $oznameni, VzhledWebuEnum $vzhled, string $cesta, array $query): string
    {
        if ($oznameni === []) {
            return '';
        }

        $sablona = new XTemplate(WWW . '/sablony/' . $vzhled->getAdresarSablon() . '/oznameni.xtpl');
        foreach ($oznameni as $jedno) {
            $sablona->assign([
                'typ'       => $jedno->typ->value,
                'nazevTypu' => $jedno->typ->getNazev(),
                // Markdown like the pages of the web, written by organizers in the admin, so it may hold links
                'text' => markdown($jedno->text),
            ]);
            if ($jedno->typ->lzeSkryt()) {
                $sablona->assign('urlSkryti', htmlspecialchars(SkrytaOznameniNaWebu::getUrlSkryti($cesta, $query, $jedno)));
                $sablona->parse('oznameni.jedno.skryt');
            }
            $sablona->parse('oznameni.jedno');
        }
        $sablona->parse('oznameni');

        return $sablona->text('oznameni');
    }
}
