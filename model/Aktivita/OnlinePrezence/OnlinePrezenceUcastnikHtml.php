<?php declare(strict_types=1);

namespace Gamecon\Aktivita\OnlinePrezence;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Aktivita\ZmenaPrihlaseni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

class OnlinePrezenceUcastnikHtml
{
    /** @var \XTemplate */
    private $onlinePrezenceUcastnikTemplate;
    /** @var int */
    private $naPosledniChviliXMinutPredZacatkem;

    public function __construct(SystemoveNastaveni $systemoveNastaveni) {
        $this->naPosledniChviliXMinutPredZacatkem = $systemoveNastaveni->prihlaseniNaPosledniChviliXMinutPredZacatkemAktivity();
    }

    public function sestavHmlUcastnikaAktivity(
        \Uzivatel $ucastnik,
        Aktivita  $aktivita,
        int       $stavPrihlaseni,
        bool      $muzeMenitUcastniky
    ): string {
        $ucastnikTemplate = $this->dejOnlinePrezenceUcastnikTemplate();

        $ucastnikTemplate->assign('u', $ucastnik);
        $ucastnikTemplate->assign('a', $aktivita);

        $ucastnikTemplate->assign('checkedUcastnik', StavPrihlaseni::dorazil($stavPrihlaseni) ? 'checked' : '');
        $ucastnikTemplate->assign('disabledUcastnik', $muzeMenitUcastniky ? '' : 'disabled');
        $ucastnikTemplate->parse('ucastnik.checkbox');

        if ($ucastnik->gcPritomen()) {
            $ucastnikTemplate->parse('ucastnik.pritomen');
        } else {
            $ucastnikTemplate->parse('ucastnik.nepritomen');
        }

        if ($ucastnik->telefon()) {
            $ucastnikTemplate->parse('ucastnik.telefon');
        }

        if ($this->jeToNaPosledniChvili($ucastnik, $aktivita)) {
            $ucastnikTemplate->assign('minutNaPosledniChvili', $this->naPosledniChviliXMinutPredZacatkem);
            $ucastnikTemplate->parse('ucastnik.prihlasenNaPosledniChvili');
        }

        if ($ucastnik->vekKDatu($aktivita->zacatek()) < 18) {
            $ucastnikTemplate->parse('ucastnik.mladsiOsmnactiLet');
        }

        $ucastnikTemplate->assign('cssTridaDisplayNahradnik', StavPrihlaseni::dorazilJakoNahradnik($stavPrihlaseni) ? '' : 'display-none');
        $ucastnikTemplate->assign('cssTridaDisplaySledujici', StavPrihlaseni::prihlasenJakoSledujici($stavPrihlaseni) ? '' : 'display-none');

        $zmenaPrihlaseni = $aktivita->dejPrezenci()->posledniZmenaPrihlaseni($ucastnik);
        $ucastnikTemplate->assign('casPosledniZmenyPrihlaseni', $zmenaPrihlaseni ? $zmenaPrihlaseni->casZmenyProJs() : '');
        $ucastnikTemplate->assign('stavPrihlaseni', $zmenaPrihlaseni ? $zmenaPrihlaseni->typPrezenceProJs() : '');
        $ucastnikTemplate->assign('idPoslednihoLogu', $zmenaPrihlaseni ? $zmenaPrihlaseni->idLogu() : 0);
        $ucastnikTemplate->assign('email', $ucastnik->mail());

        $ucastnikTemplate->parse('ucastnik');
        return $ucastnikTemplate->text('ucastnik');
    }

    private function jeToNaPosledniChvili(\Uzivatel $ucastnik, Aktivita $aktivita): bool {
        $prihlasenOd = $aktivita->prihlasenOd($ucastnik);
        $odKdyJeToNaPosledniChvili = $this->odKdyJeToNaPosledniChvili($aktivita);
        return $prihlasenOd && $odKdyJeToNaPosledniChvili && $prihlasenOd >= $odKdyJeToNaPosledniChvili;
    }

    private function odKdyJeToNaPosledniChvili(Aktivita $aktivita): ?\DateTimeInterface {
        $zacatek = $aktivita->zacatek();
        if (!$zacatek) {
            return null;
        }
        return (clone $zacatek)->modify('-' . $this->naPosledniChviliXMinutPredZacatkem . ' minutes');
    }

    private function dejOnlinePrezenceUcastnikTemplate(): \XTemplate {
        if ($this->onlinePrezenceUcastnikTemplate === null) {
            $this->onlinePrezenceUcastnikTemplate = new \XTemplate(__DIR__ . '/templates/online-prezence-ucastnik.xtpl');
        }
        return $this->onlinePrezenceUcastnikTemplate;
    }
}
