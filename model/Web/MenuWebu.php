<?php

declare(strict_types=1);

namespace Gamecon\Web;

use Gamecon\Aktivita\TypAktivity;
use Gamecon\Pravo;
use Gamecon\Uzivatel\ZpusobZobrazeniNaWebu;
use Gamecon\XTemplate\XTemplate;

/**
 * Vyrenderuje horní navigaci veřejného webu (blackarrow {menu}).
 *
 * Hlavička je na všech veřejných stránkách stejná — liší se jen podle
 * přihlášeného uživatele a viditelných typů aktivit, nezávisí na konkrétní
 * stránce. Logika byla původně inline ve web/index.php; vytažením sem ji může
 * sdílet i Symfony controller, který renderuje vybrané veřejné stránky.
 */
class MenuWebu
{
    public static function html(?\Uzivatel $uzivatel): string
    {
        $sablona = new XTemplate(WWW . '/sablony/blackarrow/menu.xtpl');

        $typy = \serazenePodle(TypAktivity::zViditelnych(), 'poradi');
        $sablona->parseEach($typy, 'typ', 'menu.typAktivit');

        if ($uzivatel) {
            $jmenoDoMenu = match ($uzivatel->zpusobZobrazeniNaWebu()) {
                ZpusobZobrazeniNaWebu::JMENO_A_PRIJMENI => $uzivatel->celeJmeno(),
                ZpusobZobrazeniNaWebu::POUZE_PREZDIVKA,
                ZpusobZobrazeniNaWebu::JMENO_S_PREZDIVKOU_A_PRIJMENI => $uzivatel->nick(),
            };
            if ($jmenoDoMenu === '') {
                $jmenoDoMenu = $uzivatel->jmenoNaWebu();
            }

            $sablona->assign([
                'u' => $uzivatel,
            ]);
            $sablona->assign([
                'uJmenoMenu' => $jmenoDoMenu,
            ]);
            $sablona->assign([
                'gcPrihlaska' => $uzivatel->gcPrihlasen() ? 'Upravit přihlášku' : 'Přihláška na GC',
            ]);
            if ($uzivatel->maPravo(Pravo::ADMINISTRACE_INFOPULT)) {
                $sablona->assign([
                    'uvodniAdminUrl' => $uzivatel->uvodniAdminUrl(),
                ]);
                $sablona->parse('menu.prihlasen.admin');
            } elseif ($uzivatel->maPravo(Pravo::ADMINISTRACE_MOJE_AKTIVITY)) {
                $sablona->assign([
                    'mojeAktivityAdminUrl' => $uzivatel->mojeAktivityAdminUrl(),
                ]);
                $sablona->parse('menu.prihlasen.mujPrehled');
            }

            $sablona->parse('menu.prihlasen');
        } else {
            $sablona->parse('menu.neprihlasen');
            $sablona->assign([
                'gcPrihlaska' => 'Přihláška na GC',
            ]);
        }

        $sablona->parse('menu');

        return $sablona->text('menu');
    }
}
