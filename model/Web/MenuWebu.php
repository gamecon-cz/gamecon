<?php

declare(strict_types=1);

namespace Gamecon\Web;

use Gamecon\Aktivita\TypAktivity;
use Gamecon\Jazyk\AktualniJazyk;
use Gamecon\Jazyk\Jazyk;
use Gamecon\Jazyk\JazykPozadavku;
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

        self::parsePrepinacJazyka($sablona);

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
                'gcPrihlaska' => $uzivatel->gcPrihlasen() ? \t('menu.upravit_prihlasku') : \t('menu.prihlaska_na_gc'),
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
                'gcPrihlaska' => \t('menu.prihlaska_na_gc'),
            ]);
        }

        $sablona->parse('menu');

        return $sablona->text('menu');
    }

    /**
     * Odkaz na tutéž stránku v ostatních jazycích. Popisek je v cílovém jazyce,
     * aby ho našel i ten, kdo jazyk aktuální stránky neumí.
     */
    private static function parsePrepinacJazyka(XTemplate $sablona): void
    {
        $jazykPozadavku = JazykPozadavku::aktualni();
        $aktualniJazyk = AktualniJazyk::jazyk();
        foreach (Jazyk::cases() as $jazyk) {
            if ($jazyk === $aktualniJazyk) {
                continue;
            }
            $sablona->assign([
                'jazykUrl'     => htmlspecialchars(
                    $jazykPozadavku?->urlPrepnuti($jazyk)
                    ?? \urlWebu('', $jazyk),
                ),
                'jazykTitulek' => htmlspecialchars(\t('jazyk.prepnout', [], $jazyk)),
                'jazykKod'     => $jazyk->value,
                'jazykNazev'   => $jazyk->vlastniNazev(),
            ]);
            $sablona->parse('menu.jazyk');
        }
    }
}
