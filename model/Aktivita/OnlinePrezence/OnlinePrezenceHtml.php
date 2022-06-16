<?php declare(strict_types=1);

namespace Gamecon\Aktivita\OnlinePrezence;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\AktivitaPrezence;
use Gamecon\Aktivita\RazitkoPosledniZmenyPrihlaseni;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Pravo;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Symfony\Component\Filesystem\Filesystem;

class OnlinePrezenceHtml
{
    /** @var \XTemplate */
    private $onlinePrezenceTemplate;
    /** @var string */
    private $jsVyjimkovac;
    /** @var null|OnlinePrezenceUcastnikHtml */
    private $onlinePrezenceUcastnikHtml;
    /** @var bool */
    private $muzemeTestovat;
    /** @var bool */
    private $testujeme;
    /** @var SystemoveNastaveni */
    private $systemoveNastaveni;
    /** @var Filesystem */
    private $filesystem;

    public function __construct(
        string             $jsVyjimkovac,
        SystemoveNastaveni $systemoveNastaveni,
        Filesystem         $filesystem,
        bool               $muzemeTestovat = false,
        bool               $testujeme = false
    ) {
        $this->jsVyjimkovac = $jsVyjimkovac;
        $this->systemoveNastaveni = $systemoveNastaveni;
        $this->filesystem = $filesystem;
        $this->muzemeTestovat = $muzemeTestovat;
        $this->testujeme = $muzemeTestovat && $testujeme;
    }

    public function dejHtmlOnlinePrezence(
        \Uzivatel $editujici,
        array     $organizovaneAktivity
    ): string {
        $template = $this->dejOnlinePrezenceTemplate();

        if ($this->muzemeTestovat) {
            if ($this->testujeme) {
                $template->assign('urlBezTestu', getCurrentUrlWithQuery(['test' => 0]));
                $template->parse('onlinePrezence.test.odkazBezTestu');
            } else {
                $template->assign('urlTest', getCurrentUrlWithQuery(['test' => 1]));
                $template->parse('onlinePrezence.test.odkazNaTest');
            }
            $template->parse('onlinePrezence.test');
        }

        ['url' => $urlZpet, 'nazev' => $textZpet] = $editujici->mimoMojeAktivityUvodniAdminUrl(URL_ADMIN, URL_WEBU);
        $template->assign('urlZpet', $urlZpet);
        $template->assign('textZpet', $textZpet);
        $template->assign('jsVyjimkovac', $this->jsVyjimkovac);

        $this->pridejLokalniAssety($template);

        if (count($organizovaneAktivity) === 0) {
            $template->parse('onlinePrezence.zadnaAktivita');
        } else {
            $this->sestavHtmlOnlinePrezence($template, $editujici, $organizovaneAktivity);
        }

        $template->parse('onlinePrezence');
        return $template->text('onlinePrezence');
    }

    private function pridejLokalniAssety(\XTemplate $template) {
        static $localAssets = [
            'stylesheets' => [
                __DIR__ . '/../../../admin/files/design/hint.css',
                __DIR__ . '/../../../admin/files/design/zvyraznena-tabulka.css',
                __DIR__ . '/../../../admin/files/design/online-prezence.css',
            ],
            'javascripts' => [
                __DIR__ . '/../../../admin/files/omnibox-1.1.1.js',
                __DIR__ . '/../../../admin/files/online-prezence/online-prezence-heat-colors.js',
                __DIR__ . '/../../../admin/files/online-prezence/online-prezence-tooltip.js',
                __DIR__ . '/../../../admin/files/online-prezence/online-prezence.js',
                __DIR__ . '/../../../admin/files/online-prezence/online-prezence-posledni-zname-zmeny-prihlaseni.js',
                __DIR__ . '/../../../admin/files/online-prezence/online-prezence-navod.js',
                __DIR__ . '/../../../admin/files/online-prezence/online-prezence-errors.js',
            ],
        ];
        foreach ($localAssets['stylesheets'] as $stylesheet) {
            $template->assign('url', str_replace(__DIR__ . '/../../../admin/', '', $stylesheet));
            $template->assign('version', md5_file($stylesheet));
            $template->parse('onlinePrezence.stylesheet');
        }
        foreach ($localAssets['javascripts'] as $javascript) {
            $template->assign('url', str_replace(__DIR__ . '/../../../admin/', '', $javascript));
            $template->assign('version', md5_file($javascript));
            $template->parse('onlinePrezence.javascript');
        }
    }

    private function dejOnlinePrezenceTemplate(): \XTemplate {
        if ($this->onlinePrezenceTemplate === null) {
            $this->onlinePrezenceTemplate = new \XTemplate(__DIR__ . '/templates/online-prezence.xtpl');
        }
        return $this->onlinePrezenceTemplate;
    }

    /**
     * @param \XTemplate $template
     * @param Aktivita[] $organizovaneAktivity
     * @return void
     */
    private function sestavHtmlOnlinePrezence(
        \XTemplate $template,
        \Uzivatel  $vypravec,
        array      $organizovaneAktivity
    ) {
        foreach ($organizovaneAktivity as $aktivita) {
            $editovatelnaOdTimestamp = $this->dejEditovatelnaOdTimestamp($aktivita);
            $editovatelnaDoTimestamp = $this->dejEditovatelnaDoTimestamp($aktivita);
            $nekdoUzDorazil = $aktivita->nekdoUzDorazil();
            $nikdoZatimNedorazil = !$nekdoUzDorazil;
            $editovatelnaHned = $editovatelnaOdTimestamp === 0 && $editovatelnaDoTimestamp > 0;
            $uzProbehla = $editovatelnaOdTimestamp === 0 && $editovatelnaDoTimestamp === 0;
            $jesteNeprobehla = !$uzProbehla;
            $zamcena = $aktivita->zamcena();
            $odemcena = !$zamcena;
            $maPravoNaZmenuHistorie = $vypravec->maPravoNaZmenuHistorieAktivit();
            $nemaPravoNaZmenuHistorie = !$maPravoNaZmenuHistorie;
            $muzeMenitUcastnikyHned = $editovatelnaHned && ($odemcena || $nikdoZatimNedorazil || $maPravoNaZmenuHistorie);
            $nemuzeMenitUcastnikyHned = !$muzeMenitUcastnikyHned;
            $zmenaStavuAktivity = $aktivita->posledniZmenaStavuAktivity();
            $konec = $aktivita->konec();

            $template->assign(
                'omniboxUrl',
                getCurrentUrlWithQuery(['ajax' => 1, 'omnibox' => 1, 'idAktivity' => $aktivita->id()])
            );
            $template->assign('konecAktivityVTimestamp', $konec ? $konec->getTimestamp() : null);
            $template->assign('editovatelnaDoTimestamp', $editovatelnaDoTimestamp);
            $template->assign('editovatelnaOdTimestamp', $editovatelnaOdTimestamp);
            $template->assign('maPravoNaZmenuHistorie', $maPravoNaZmenuHistorie);
            $template->assign('minutNaPosledniChvili', $this->systemoveNastaveni->prihlaseniNaPosledniChviliXMinutPredZacatkemAktivity());
            $template->assign('kapacita', (int)$aktivita->kapacita());
            $template->assign('idAktivity', $aktivita->id());
            $template->assign('casPosledniZmenyStavuAktivity', $zmenaStavuAktivity ? $zmenaStavuAktivity->casZmenyProJs() : '');
            $template->assign('stavAktivity', $zmenaStavuAktivity ? $zmenaStavuAktivity->stavAktivityProJs() : '');
            $template->assign('idPoslednihoLogu', $zmenaStavuAktivity ? $zmenaStavuAktivity->idLogu() : 0);

            // 🔒 Uzavřena pro online přihlašování 🔒
            $template->assign('displayNoneCssClassUzavrena', $this->dejCssClassNeviditelnosti($odemcena));
            // Spustit a zamkout 🔒
            $template->assign(
                'displayNoneCssClassUzavrit',
                $this->dejCssClassNeviditelnosti($zamcena || !$editovatelnaHned || $uzProbehla)
            );
            // ⏳ Můžeš ji editovat za ⏳
            $template->assign(
                'displayNoneCssClassCeka',
                $this->dejCssClassNeviditelnosti($zamcena || $editovatelnaHned || $uzProbehla)
            );
            // 💨 Proběhla bez povšimnutí 💨
            $template->assign(
                'displayNoneCssClassProbehlaBezPovsimnuti',
                $this->dejCssClassNeviditelnosti($zamcena || $editovatelnaHned || $jesteNeprobehla)
            );
            // ✋ Aktivita už skončila, pozor na úpravy ✋
            $template->assign(
                'displayNoneCssClassAktivitaSkoncila',
                //zobrazíme pouze v případě, že aktivitu lze editovat i po skončení
                $this->dejCssClassNeviditelnosti($nemuzeMenitUcastnikyHned || !$konec || ($konec > $this->systemoveNastaveni->ted()))
            );

            foreach ($this->seradDleStavuPrihlaseni($aktivita->prihlaseni(), $aktivita) as $prihlasenyUzivatel) {
                $ucastnikHtml = $this->dejOnlinePrezenceUcastnikHtml()->sestavHmlUcastnikaAktivity(
                    $prihlasenyUzivatel,
                    $aktivita,
                    $aktivita->stavPrihlaseni($prihlasenyUzivatel),
                    $muzeMenitUcastnikyHned
                );
                $template->assign('ucastnikHtml', $ucastnikHtml);
                $template->parse('onlinePrezence.aktivity.aktivita.form.ucastnik');
            }

            // ⚠️Pozor, aktivita už je uzavřená! ⚠
            $template->assign(
                'displayNoneCssClassPozorUzavrena',
                $this->dejCssClassNeviditelnosti($odemcena || $nikdoZatimNedorazil || ($editovatelnaDoTimestamp <= 0 && $nemaPravoNaZmenuHistorie))
            );
            $template->assign('disabledPridatUcastnika', $muzeMenitUcastnikyHned ? '' : 'disabled');
            $template->assign('idAktivity', $aktivita->id());
            $template->parse('onlinePrezence.aktivity.aktivita.form.pridatUcastnika');

            $template->assign('nadpis', implode(' – ', array_filter([$aktivita->nazev(), $aktivita->orgJmena(), $aktivita->lokace()])));
            $template->assign('zacatek', $aktivita->zacatek() ? $aktivita->zacatek()->format('l H:i') : '-nevíme-');
            $template->assign('konec', $aktivita->konec() ? $aktivita->konec()->format('l H:i') : '-nevíme-');
            $template->parse('onlinePrezence.aktivity.aktivita.form');

            $template->parse('onlinePrezence.aktivity.aktivita');
        }
        $razitkoPosledniZmeny = new RazitkoPosledniZmenyPrihlaseni(
            $vypravec,
            Aktivita::posledniZmenaStavuAktivit($organizovaneAktivity),
            AktivitaPrezence::posledniZmenaPrihlaseniAktivit(
                null, // bereme každého účastníka
                $organizovaneAktivity
            ),
            $this->filesystem,
            OnlinePrezenceAjax::RAZITKO_POSLEDNI_ZMENY
        );
        $template->assign('razitkoPosledniZmeny', $razitkoPosledniZmeny->dejPotvrzeneRazitkoPosledniZmeny());
        $template->assign('urlRazitkaPosledniZmeny', $razitkoPosledniZmeny->dejUrlRazitkaPosledniZmeny());
        $template->assign('urlAkcePosledniZmeny', OnlinePrezenceAjax::dejUrlAkcePosledniZmeny());
        $template->assign('posledniLogyAktivitAjaxKlic', OnlinePrezenceAjax::POSLEDNI_LOGY_AKTIVIT_AJAX_KLIC);
        $template->assign('posledniLogyUcastnikuAjaxKlic', OnlinePrezenceAjax::POSLEDNI_LOGY_UCASTNIKU_AJAX_KLIC);

        $template->parse('onlinePrezence.aktivity');
    }

    /**
     * @param \Uzivatel[] $prihlaseni
     * @return \Uzivatel[]
     */
    private function seradDleStavuPrihlaseni(array $prihlaseni, Aktivita $aktivita): array {
        usort($prihlaseni, function (\Uzivatel $nejakyPrihlaseny, $jinyPrihlaseny) use ($aktivita) {
            // běžní účastníci první, náhradníci druzí, sledující poslední
            $porovnaniStavuPrihlaseni = $aktivita->stavPrihlaseni($nejakyPrihlaseny) <=> $aktivita->stavPrihlaseni($jinyPrihlaseny);
            if ($porovnaniStavuPrihlaseni !== 0) {
                return $porovnaniStavuPrihlaseni;
            }
            $prezence = $aktivita->dejPrezenci();
            // později přidaný / změněný nakonec
            return $prezence->posledniZmenaPrihlaseni($nejakyPrihlaseny)->idLogu() <=> $prezence->posledniZmenaPrihlaseni($jinyPrihlaseny)->idLogu();
        });
        return $prihlaseni;
    }

    private function dejEditovatelnaOdTimestamp(Aktivita $aktivita): int {
        $zacatek = $aktivita->zacatek();
        $hnedEditovatelnaSeZacatkemDo = $zacatek
            ? (clone $zacatek)->modify("-{$this->systemoveNastaveni->aktivitaEditovatelnaXMinutPredJejimZacatkem()} minutes")
            : null;
        if (!$hnedEditovatelnaSeZacatkemDo) {
            return 0;
        }
        return $hnedEditovatelnaSeZacatkemDo <= $this->systemoveNastaveni->ted()
            ? 0 // aktivitu může editovat hned
            // pokud je editovatelná například od 12:10, ale "teď" je 12:00, tak musíme počkat oněch rozdílových 10 minut
            : time() + ($hnedEditovatelnaSeZacatkemDo->getTimestamp() - $this->systemoveNastaveni->ted()->getTimestamp());
    }

    private function dejEditovatelnaDoTimestamp(Aktivita $aktivita): int {
        $editovatelnaDo = $aktivita->editovatelnaDo($this->systemoveNastaveni);
        return $editovatelnaDo <= $this->systemoveNastaveni->ted()
            ? 0 // už ji nelze editovat
            : $editovatelnaDo->getTimestamp();
    }

    private function dejCssClassNeviditelnosti(bool $skryt) {
        return $skryt ? 'display-none' : '';
    }

    private function dejOnlinePrezenceUcastnikHtml(): OnlinePrezenceUcastnikHtml {
        if (!$this->onlinePrezenceUcastnikHtml) {
            $this->onlinePrezenceUcastnikHtml = new OnlinePrezenceUcastnikHtml($this->systemoveNastaveni);
        }
        return $this->onlinePrezenceUcastnikHtml;
    }

    public function sestavHmlUcastnikaAktivity(
        \Uzivatel $ucastnik,
        Aktivita  $aktivita,
        int       $stavPrihlaseni,
        bool      $zatimPouzeProCteni
    ): string {
        return $this->dejOnlinePrezenceUcastnikHtml()
            ->sestavHmlUcastnikaAktivity($ucastnik, $aktivita, $stavPrihlaseni, $zatimPouzeProCteni);
    }
}
