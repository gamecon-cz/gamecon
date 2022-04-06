<?php declare(strict_types=1);

namespace Gamecon\Aktivita\OnlinePrezence;

use Gamecon\Aktivita\Aktivita;

class OnlinePrezenceHtml
{
    /** @var \XTemplate */
    private $onlinePrezenceTemplate;
    /** @var \XTemplate */
    private $onlinePrezenceUcastnikTemplate;

    public function dejHtmlOnlinePrezence(
        array              $aktivity,
        int                $editovatelnaXMinutPredZacatkem = 20,
        \DateTimeInterface $now = null,
        string             $urlZpet = null,
        string             $ajaxUrl = null
    ): string {
        $template = $this->dejOnlinePrezenceTemplate();

        $template->assign('urlZpet', $urlZpet ?? getBackUrl());

        if (count($aktivity) === 0) {
            $template->parse('onlinePrezence.zadnaAktivita');
        } else {
            $template->assign('omniboxUrl', $ajaxUrl ?? getCurrentUrlPath());
            $this->sestavHtmlOnlinePrezence($template, $aktivity, $editovatelnaXMinutPredZacatkem, $now);
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
        array               $aktivity,
        int                 $editovatelnaXMinutPredZacatkem,
        ?\DateTimeInterface $now
    ) {
        $now = $now ?? new \DateTimeImmutable();

        foreach ($aktivity as $aktivita) {
            $editovatelnaOdTimestamp = self::dejEditovatelnaOdTimestamp($aktivita, $editovatelnaXMinutPredZacatkem, $now);
            $editovatelnaHned = $editovatelnaOdTimestamp > 0;
            $zamcena = $aktivita->zamcena();

            // 🔒 Uzavřena pro online přihlašování 🔒
            $template->assign('displayNoneCssClassUzavrena', $this->dejCssClassNeviditelnosti($zamcena));
            // Spustit a zamkout 🔒
            $template->assign('displayNoneCssClassUzavrit', $this->dejCssClassNeviditelnosti(!$zamcena && $editovatelnaHned));
            $template->assign('uzavrena', $zamcena);
            // ⏳ Můžeš ji editovat za ⏳
            $template->assign('editovatelnaOdTimestamp', $editovatelnaOdTimestamp);
            $template->assign('displayNoneCssClassCeka', $this->dejCssClassNeviditelnosti(!$zamcena && !$editovatelnaHned));

            foreach ($aktivita->prihlaseni() as $prihlasenyUzivatel) {
                $ucastnikHtml = $this->sestavHmlUcastnikaAktivity(
                    $prihlasenyUzivatel,
                    $aktivita,
                    $aktivita->dorazilJakoCokoliv($prihlasenyUzivatel),
                    !$editovatelnaHned
                );
                $template->assign('ucastnikHtml', $ucastnikHtml);
                $template->parse('onlinePrezence.aktivity.aktivita.form.ucastnik');
            }

            if (!$zamcena) {
                $template->assign('disabledPridatUcastnika', $editovatelnaHned ? '' : 'disabled');
                $template->assign('idAktivity', $aktivita->id());
                $template->assign('editovatelnaOd', $editovatelnaOdTimestamp);
                $template->parse('onlinePrezence.aktivity.aktivita.form.pridatUcastnika');
            }

            $template->assign('nadpis', implode(' – ', array_filter([$aktivita->nazev(), $aktivita->orgJmena(), $aktivita->lokace()])));
            $template->parse('onlinePrezence.aktivity.aktivita.form');

            $template->parse('onlinePrezence.aktivity.aktivita');
        }
        $template->parse('onlinePrezence.aktivity');
    }

    private static function dejEditovatelnaOdTimestamp(Aktivita $aktivita, int $editovatelnaXMinutPredZacatkem, ?\DateTimeInterface $now): int {
        $now = $now ?? new \DateTimeImmutable();
        $zacatek = $aktivita->zacatek();
        $hnedEditovatelnaSeZaCatkemDo = $zacatek ?
            (clone $zacatek)->modify("-{$editovatelnaXMinutPredZacatkem} minutes")
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

    public function sestavHmlUcastnikaAktivity(
        \Uzivatel $ucastnik,
        Aktivita $aktivita,
        bool      $dorazil,
        bool      $zatimPouzeProCteni
    ): string {
        $ucastnikTemplate = $this->dejOnlinePrezenceUcastnikTemplate();

        $ucastnikTemplate->assign('u', $ucastnik);
        $ucastnikTemplate->assign('a', $aktivita);

        $ucastnikTemplate->assign('checkedUcastnik', $dorazil ? 'checked' : '');
        $ucastnikTemplate->assign('disabledUcastnik', $zatimPouzeProCteni || $aktivita->zamcena() ? 'disabled' : '');
        $ucastnikTemplate->parse('ucastnik.checkbox');

        $ucastnikTemplate->parse('ucastnik.' . ($ucastnik->gcPritomen() ? 'pritomen' : 'nepritomen'));
        if ($ucastnik->telefon()) {
            $ucastnikTemplate->parse('ucastnik.telefon');
        }
        $ucastnikTemplate->parse('ucastnik');
        return $ucastnikTemplate->text('ucastnik');
    }

    private function dejOnlinePrezenceUcastnikTemplate(): \XTemplate {
        if ($this->onlinePrezenceUcastnikTemplate === null) {
            $this->onlinePrezenceUcastnikTemplate = new \XTemplate(__DIR__ . '/templates/online-prezence-ucastnik.xtpl');
        }
        return $this->onlinePrezenceUcastnikTemplate;
    }
}
