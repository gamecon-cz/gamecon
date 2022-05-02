<?php declare(strict_types=1);

namespace Gamecon\Aktivita\OnlinePrezence;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\ZmenaStavuPrihlaseni;

class OnlinePrezenceUcastnikHtml
{
    /** @var \XTemplate */
    private $onlinePrezenceUcastnikTemplate;
    /** @var int */
    private $naPosledniChviliXMinutPredZacatkem;

    public function __construct(int $naPosledniChviliXMinutPredZacatkem) {
        $this->naPosledniChviliXMinutPredZacatkem = $naPosledniChviliXMinutPredZacatkem;
    }

    public function sestavHmlUcastnikaAktivity(
        \Uzivatel $ucastnik,
        Aktivita  $aktivita,
        bool      $dorazil,
        bool      $zatimPouzeProCteni
    ): string {
        $ucastnikTemplate = $this->dejOnlinePrezenceUcastnikTemplate();

        $ucastnikTemplate->assign('u', $ucastnik);
        $ucastnikTemplate->assign('a', $aktivita);

        $ucastnikTemplate->assign('checkedUcastnik', $dorazil ? 'checked' : '');
        $ucastnikTemplate->assign('disabledUcastnik', $zatimPouzeProCteni || $aktivita->zamcena() ? 'disabled' : '');
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

        $zmenaStavuPrihlaseni = $aktivita->dejPrezenci()->dejPosledniZmenaStavuPrihlaseni($ucastnik);
        $ucastnikTemplate->assign('casPosledniZmenyPrihlaseni', $zmenaStavuPrihlaseni->casZmenyProJs());
        $ucastnikTemplate->assign('stavPrihlaseni', $zmenaStavuPrihlaseni->stavPrihlaseniProJs());

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
