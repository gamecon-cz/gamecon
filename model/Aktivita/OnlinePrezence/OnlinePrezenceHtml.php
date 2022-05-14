<?php declare(strict_types=1);

namespace Gamecon\Aktivita\OnlinePrezence;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\AktivitaPrezence;
use Gamecon\Aktivita\RazitkoPosledniZmenyPrihlaseni;
use Gamecon\Pravo;
use Symfony\Component\Filesystem\Filesystem;

class OnlinePrezenceHtml
{
    /** @var \XTemplate */
    private $onlinePrezenceTemplate;
    /** @var string */
    private $jsVyjimkovac;
    /** @var int */
    private $naPosledniChviliXMinutPredZacatkem;
    /** @var null|OnlinePrezenceUcastnikHtml */
    private $onlinePrezenceUcastnikHtml;
    /** @var bool */
    private $muzemeTestovat;
    /** @var bool */
    private $testujeme;

    public function __construct(
        string $jsVyjimkovac,
        int    $naPosledniChviliXMinutPredZacatkem,
        bool   $muzemeTestovat = false,
        bool   $testujeme = false
    ) {
        $this->jsVyjimkovac = $jsVyjimkovac;
        $this->naPosledniChviliXMinutPredZacatkem = $naPosledniChviliXMinutPredZacatkem;
        $this->muzemeTestovat = $muzemeTestovat;
        $this->testujeme = $muzemeTestovat && $testujeme;
    }

    public function dejHtmlOnlinePrezence(
        \Uzivatel          $editujici,
        array              $aktivity,
        int                $editovatelnaXMinutPredZacatkem = 20,
        \DateTimeInterface $now = null
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

        if (count($aktivity) === 0) {
            $template->parse('onlinePrezence.zadnaAktivita');
        } else {
            $this->sestavHtmlOnlinePrezence($template, $editujici, $aktivity, $editovatelnaXMinutPredZacatkem, $now);
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
                __DIR__ . '/../../../admin/files/bootstrap-tooltip-initialization.js',
                __DIR__ . '/../../../admin/files/omnibox.js',
                __DIR__ . '/../../../admin/files/online-prezence.js',
                __DIR__ . '/../../../admin/files/online-prezence-posledni-zname-zmeny-prihlaseni.js',
                __DIR__ . '/../../../admin/files/online-prezence-navod.js',
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
     * @param array|Aktivita[] $organizovaneAktivity
     * @param int $editovatelnaXMinutPredZacatkem
     * @param \DateTimeInterface|null $now
     * @return void
     */
    private function sestavHtmlOnlinePrezence(
        \XTemplate          $template,
        \Uzivatel           $vypravec,
        array               $organizovaneAktivity,
        int                 $editovatelnaXMinutPredZacatkem,
        ?\DateTimeInterface $now
    ) {
        $now = $now ?? new \DateTimeImmutable();
        $filesystem = new Filesystem();

        foreach ($organizovaneAktivity as $aktivita) {
            $editovatelnaOdTimestamp = self::dejEditovatelnaOdTimestamp($aktivita, $editovatelnaXMinutPredZacatkem, $now);
            $nekdoUzDorazil = $aktivita->nekdoUzDorazil();
            $nikdoZatimNedorazil = !$nekdoUzDorazil;
            $editovatelnaHned = !$editovatelnaOdTimestamp;
            $zamcena = $aktivita->zamcena();
            $maPravoNaZmenuHistorie = $vypravec->maPravo(Pravo::ZMENA_HISTORIE_AKTIVIT);
            $muzeMenitUcastniky = $editovatelnaHned && (!$zamcena || $nikdoZatimNedorazil || $maPravoNaZmenuHistorie);

            $template->assign('omniboxUrl', getCurrentUrlWithQuery(['ajax' => 1, 'omnibox' => 1, 'idAktivity' => $aktivita->id()]));

            // 🔒 Uzavřena pro online přihlašování 🔒
            $template->assign('displayNoneCssClassUzavrena', $this->dejCssClassNeviditelnosti($zamcena));
            // Spustit a zamkout 🔒
            $template->assign('displayNoneCssClassUzavrit', $this->dejCssClassNeviditelnosti(!$zamcena && $editovatelnaHned));
            // ⏳ Můžeš ji editovat za ⏳
            $template->assign('editovatelnaOdTimestamp', $editovatelnaOdTimestamp);
            $template->assign('displayNoneCssClassCeka', $this->dejCssClassNeviditelnosti(!$zamcena && !$editovatelnaHned));

            if ($muzeMenitUcastniky) { // nechceme zobrazovat varovbání tomu, kdo beztak nemůže nic upravovat
                $konec = $aktivita->konec();
                $template->assign('konecAktivityVTimestamp', $konec ? $konec->getTimestamp() : null);
                $template->assign('displayNoneCssClassAktivitaSkoncila', $this->dejCssClassNeviditelnosti($konec && $konec <= $now));
                // ✋ AKTIVITA UŽ SKONČILA, POZOR NA ÚPRAVY ✋
                $template->parse('onlinePrezence.aktivity.aktivita.pozorNaKonecAktivity');
            }

            foreach ($aktivita->prihlaseni() as $prihlasenyUzivatel) {
                $ucastnikHtml = $this->dejOnlinePrezenceUcastnikHtml()->sestavHmlUcastnikaAktivity(
                    $prihlasenyUzivatel,
                    $aktivita,
                    $aktivita->dorazilJakoCokoliv($prihlasenyUzivatel),
                    $muzeMenitUcastniky
                );
                $template->assign('ucastnikHtml', $ucastnikHtml);
                $template->parse('onlinePrezence.aktivity.aktivita.form.ucastnik');
            }

            // ⚠️Pozor, aktivita už je vyplněná! ⚠
            $template->assign(
                'displayNoneCssClassPozorVyplnena',
                $this->dejCssClassNeviditelnosti($zamcena && $nekdoUzDorazil && $maPravoNaZmenuHistorie)
            );
            $template->assign('disabledPridatUcastnika', $muzeMenitUcastniky ? '' : 'disabled');
            $template->assign('idAktivity', $aktivita->id());
            $template->parse('onlinePrezence.aktivity.aktivita.form.pridatUcastnika');

            $template->assign('nadpis', implode(' – ', array_filter([$aktivita->nazev(), $aktivita->orgJmena(), $aktivita->lokace()])));
            $template->assign('minutNaPosledniChvili', $this->naPosledniChviliXMinutPredZacatkem);
            $template->parse('onlinePrezence.aktivity.aktivita.form');

            $template->parse('onlinePrezence.aktivity.aktivita');
        }
        $razitkoPosledniZmeny = new RazitkoPosledniZmenyPrihlaseni(
            $vypravec,
            AktivitaPrezence::posledniZmenaStavuPrihlaseniAktivit(
                null, // bereme každého účastníka
                $organizovaneAktivity
            ),
            $filesystem,
            OnlinePrezenceAjax::RAZITKO_POSLEDNI_ZMENY
        );
        $template->assign('razitkoPosledniZmeny', $razitkoPosledniZmeny->dejPotvrzeneRazitkoPosledniZmeny());
        $template->assign('urlRazitkaPosledniZmeny', $razitkoPosledniZmeny->dejUrlRazitkaPosledniZmeny());
        $template->assign('urlAkcePosledniZmeny', OnlinePrezenceAjax::dejUrlAkcePosledniZmeny());
        $template->assign('idsPoslednichLoguUcastnikuAjaxKlic', OnlinePrezenceAjax::IDS_POSLEDNICH_LOGU_UCASTNIKU_AJAX_KLIC);

        $template->parse('onlinePrezence.aktivity');
    }

    private static function dejEditovatelnaOdTimestamp(Aktivita $aktivita, int $editovatelnaXMinutPredZacatkem, \DateTimeInterface $now): int {
        $zacatek = $aktivita->zacatek();
        $hnedEditovatelnaSeZaCatkemDo = $zacatek
            ? (clone $zacatek)->modify("-{$editovatelnaXMinutPredZacatkem} minutes")
            : null;
        $editovatelnaHned = !$hnedEditovatelnaSeZaCatkemDo || $hnedEditovatelnaSeZaCatkemDo <= $now;
        $editovatelnaOdTimestamp = $editovatelnaHned
            ? 0 // aktivitu může editovat hned
            // pokud například začíná v 12:10, ale editovatelné jsou etď jen ty co začínají nanejvýše do 12:00, tak musíme počkat 10 minut
            : time() + ($hnedEditovatelnaSeZaCatkemDo->getTimestamp() - $now->getTimestamp());

        return $editovatelnaOdTimestamp;
    }

    private function dejCssClassNeviditelnosti(bool $zobrazit) {
        return $zobrazit ? '' : 'display-none';
    }

    private function dejOnlinePrezenceUcastnikHtml(): OnlinePrezenceUcastnikHtml {
        if (!$this->onlinePrezenceUcastnikHtml) {
            $this->onlinePrezenceUcastnikHtml = new OnlinePrezenceUcastnikHtml($this->naPosledniChviliXMinutPredZacatkem);
        }
        return $this->onlinePrezenceUcastnikHtml;
    }

    public function sestavHmlUcastnikaAktivity(
        \Uzivatel $ucastnik,
        Aktivita  $aktivita,
        bool      $dorazil,
        bool      $zatimPouzeProCteni
    ): string {
        return $this->dejOnlinePrezenceUcastnikHtml()
            ->sestavHmlUcastnikaAktivity($ucastnik, $aktivita, $dorazil, $zatimPouzeProCteni);
    }
}
