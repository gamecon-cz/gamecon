<?php declare(strict_types=1);

namespace Gamecon\Aktivita\OnlinePrezence;

class OnlinePrezenceHtml
{
    /** @var \XTemplate */
    private $onlinePrezenceTemplate;
    /** @var \XTemplate */
    private $onlinePrezenceUcastnikTemplate;

    public function dejHtmlOnlinePrezence(array $aktivity, string $urlZpet): string {
        $template = $this->dejOnlinePrezenceTemplate();

        if (count($aktivity) === 0) {
            $template->parse('onlinePrezence.zadnaAktivita');
        } else {
            $template->assign('omniboxUrl', basename(__FILE__, '.php'));
            $template->assign('urlZpet', $urlZpet);
            $this->sestavHtmlOnlinePrezence($template, $aktivity);
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
     * @param array|\Aktivita[] $aktivity
     * @return void
     */
    private function sestavHtmlOnlinePrezence(\XTemplate $template, array $aktivity) {
        foreach ($aktivity as $aktivita) {
            $zamcena = $aktivita->zamcena();

            $template->assign('a', $aktivita);
            $template->assign('uzavrena', $zamcena);

            foreach ($aktivita->prihlaseni() as $prihlasenyUzivatel) {
                $ucastnikHtml = $this->sestavHmlUcastnikaAktivity(
                    $prihlasenyUzivatel,
                    $aktivita,
                    $aktivita->dorazilJakoCokoliv($prihlasenyUzivatel)
                );
                $template->assign('ucastnikHtml', $ucastnikHtml);
                $template->parse('onlinePrezence.aktivity.aktivita.form.ucastnik');
            }

            if (!$zamcena) {
                $template->parse('onlinePrezence.aktivity.aktivita.form.pridatUcastnika');
            }

            $template->assign('nadpis', implode(' – ', array_filter([$aktivita->nazev(), $aktivita->orgJmena(), $aktivita->lokace()])));
            $template->parse('onlinePrezence.aktivity.aktivita.form');

            $template->parse('onlinePrezence.aktivity.aktivita');
        }
        $template->parse('onlinePrezence.aktivity');
    }

    public function sestavHmlUcastnikaAktivity(
        \Uzivatel $ucastnik,
        \Aktivita $aktivita,
        bool      $dorazil
    ): string {
        $ucastnikTemplate = $this->dejOnlinePrezenceUcastnikTemplate();

        $ucastnikTemplate->assign('u', $ucastnik);
        $ucastnikTemplate->assign('a', $aktivita);

        $ucastnikTemplate->assign('checked', $dorazil ? 'checked' : '');
        $ucastnikTemplate->assign('disabled', $aktivita->zamcena() ? 'disabled' : '');
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
