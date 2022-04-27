<?php declare(strict_types=1);

namespace Gamecon\Aktivita\OnlinePrezence;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Pravo;

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

    public function __construct(string $jsVyjimkovac, int $naPosledniChviliXMinutPredZacatkem) {

        $this->jsVyjimkovac = $jsVyjimkovac;
        $this->naPosledniChviliXMinutPredZacatkem = $naPosledniChviliXMinutPredZacatkem;
    }

    public function dejHtmlOnlinePrezence(
        \Uzivatel          $editujici,
        array              $aktivity,
        int                $editovatelnaXMinutPredZacatkem = 20,
        \DateTimeInterface $now = null,
        string             $urlZpet = null
    ): string {
        $template = $this->dejOnlinePrezenceTemplate();

        $template->assign('urlZpet', $urlZpet ?? getBackUrl());
        $template->assign('jsVyjimkovac', $this->jsVyjimkovac);

        if (count($aktivity) === 0) {
            if (defined('TESTING') && TESTING) {
                $template->assign('urlTest', getCurrentUrlWithQuery(['test' => 1]));
                $template->parse('onlinePrezence.zadnaAktivita.odkazNaTest');
            }
            $template->parse('onlinePrezence.zadnaAktivita');
        } else {
            $this->sestavHtmlOnlinePrezence($template, $editujici, $aktivity, $editovatelnaXMinutPredZacatkem, $now);
        }

        $template->parse('onlinePrezence');
        return $template->text('onlinePrezence');
    }

    private function dejOnlinePrezenceTemplate(): \XTemplate {
        if ($this->onlinePrezenceTemplate === null) {
            $this->onlinePrezenceTemplate = new \XTemplate(__DIR__ . '/templates/online-prezence.xtpl');
        }
        return $this->onlinePrezenceTemplate;
    }

    /**
     * @param \XTemplate $template
     * @param array|Aktivita[] $aktivity
     * @param int $editovatelnaXMinutPredZacatkem
     * @param \DateTimeInterface|null $now
     * @return void
     */
    private function sestavHtmlOnlinePrezence(
        \XTemplate          $template,
        \Uzivatel           $editujici,
        array               $aktivity,
        int                 $editovatelnaXMinutPredZacatkem,
        ?\DateTimeInterface $now
    ) {
        $now = $now ?? new \DateTimeImmutable();

        foreach ($aktivity as $aktivita) {
            $editovatelnaOdTimestamp = self::dejEditovatelnaOdTimestamp($aktivita, $editovatelnaXMinutPredZacatkem, $now);
            $nekdoUzDorazil = $aktivita->nekdoUzDorazil();
            $nikdoZatimNedorazil = !$nekdoUzDorazil;
            $editovatelnaHned = !$editovatelnaOdTimestamp;
            $zamcena = $aktivita->zamcena();

            $template->assign('omniboxUrl', getCurrentUrlWithQuery(['ajax' => 1, 'omnibox' => 1, 'idAktivity' => $aktivita->id()]));

            // 🔒 Uzavřena pro online přihlašování 🔒
            $template->assign('displayNoneCssClassUzavrena', $this->dejCssClassNeviditelnosti($zamcena));
            // Spustit a zamkout 🔒
            $template->assign('displayNoneCssClassUzavrit', $this->dejCssClassNeviditelnosti(!$zamcena && $editovatelnaHned));
            // ⏳ Můžeš ji editovat za ⏳
            $template->assign('editovatelnaOdTimestamp', $editovatelnaOdTimestamp);
            $template->assign('displayNoneCssClassCeka', $this->dejCssClassNeviditelnosti(!$zamcena && !$editovatelnaHned));

            $konec = $aktivita->konec();
            $template->assign('konecAktivityVTimestamp', $konec ? $konec->getTimestamp() : null);
            $template->assign('displayNoneCssClassAktivitaSkoncila', $this->dejCssClassNeviditelnosti($konec && $konec <= $now));

            foreach ($aktivita->prihlaseni() as $prihlasenyUzivatel) {
                $ucastnikHtml = $this->dejOnlinePrezenceUcastnikHtml()->sestavHmlUcastnikaAktivity(
                    $prihlasenyUzivatel,
                    $aktivita,
                    $aktivita->dorazilJakoCokoliv($prihlasenyUzivatel),
                    !$editovatelnaHned
                );
                $template->assign('ucastnikHtml', $ucastnikHtml);
                $template->parse('onlinePrezence.aktivity.aktivita.form.ucastnik');
            }

            $maPravoNaZmenuHistorie = $editujici->maPravo(Pravo::ZMENA_HISTORIE_AKTIVIT);
            // ⚠️Pozor, aktivita už je vyplněná! ⚠
            $template->assign(
                'displayNoneCssClassPozorVyplnena',
                $this->dejCssClassNeviditelnosti($zamcena && $nekdoUzDorazil && $maPravoNaZmenuHistorie)
            );
            $muzePridatUcastnika = $editovatelnaHned && (!$zamcena || $nikdoZatimNedorazil || $maPravoNaZmenuHistorie);
            $template->assign('disabledPridatUcastnika', $muzePridatUcastnika ? '' : 'disabled');
            $template->assign('idAktivity', $aktivita->id());
            $template->parse('onlinePrezence.aktivity.aktivita.form.pridatUcastnika');

            $template->assign('nadpis', implode(' – ', array_filter([$aktivita->nazev(), $aktivita->orgJmena(), $aktivita->lokace()])));
            $template->assign('minutNaPosledniChvili', $this->naPosledniChviliXMinutPredZacatkem);
            $template->parse('onlinePrezence.aktivity.aktivita.form');

            $template->parse('onlinePrezence.aktivity.aktivita');
        }
        $template->assign(
            'urlPosledniZmenyPrihlaseni',
            OnlinePrezenceAjax::urlPosledniZmenyPrihlaseni(),
        );

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
