<?php declare(strict_types=1);

namespace Gamecon\Aktivita\OnlinePrezence;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\XTemplate\XTemplate;
use Uzivatel;

class OnlinePrezenceUcastnikHtml
{
    private ?XTemplate $onlinePrezenceUcastnikTemplate = null;
    private int        $naPosledniChviliXMinutPredZacatkem;

    public function __construct(private readonly SystemoveNastaveni $systemoveNastaveni)
    {
        $this->naPosledniChviliXMinutPredZacatkem = $systemoveNastaveni->prihlaseniNaPosledniChviliXMinutPredZacatkemAktivity();
    }

    public function sestavHmlUcastnikaAktivity(
        Uzivatel $ucastnik,
        Aktivita $aktivita,
        int      $stavPrihlaseni,
        bool     $ucastnikMuzeBytPridan,
        bool     $ucastnikMuzeBytOdebran,
    ): string
    {
        $ucastnikTemplate = $this->dejOnlinePrezenceUcastnikTemplate();

        $ucastnikTemplate->assign('u', $ucastnik);
        $ucastnikTemplate->assign('a', $aktivita);

        $dorazil = StavPrihlaseni::dorazilJakoCokoliv($stavPrihlaseni);
        $ucastnikTemplate->assign('checkedUcastnik', $dorazil ? 'checked' : '');
        $ucastnikTemplate->assign(
            'disabledUcastnik',
            ($dorazil && !$ucastnikMuzeBytOdebran) || (!$dorazil && !$ucastnikMuzeBytPridan)
                ? 'disabled'
                : '',
        );
        $ucastnikTemplate->parse('ucastnik.checkbox');

        if ($ucastnik->gcPritomen()) {
            $ucastnikTemplate->parse('ucastnik.pritomen');
        } else {
            $ucastnikTemplate->parse('ucastnik.nepritomen');
        }

        if ($ucastnik->telefon()) {
            $ucastnikTemplate->assign('telefonHtml', $ucastnik->telefon(true));
            $ucastnikTemplate->assign('telefonRaw', $ucastnik->telefon(false));
            $ucastnikTemplate->parse('ucastnik.telefon');
        }

        if ($this->jeToNaPosledniChvili($ucastnik, $aktivita)) {
            $ucastnikTemplate->assign('minutNaPosledniChvili', $this->naPosledniChviliXMinutPredZacatkem);
            $ucastnikTemplate->parse('ucastnik.prihlasenNaPosledniChvili');
        }

        if (($vek = $ucastnik->vekKDatu($aktivita->zacatek() ?? $this->systemoveNastaveni->konecLetosnihoGameconu())) < 18) {
            $ucastnikTemplate->assign('vek', $vek);
            $ucastnikTemplate->parse('ucastnik.mladsiOsmnactiLet');
        }

        $ucastnikTemplate->assign('cssTridaDisplayNahradnik', $this->cssZobrazitKdyz(StavPrihlaseni::dorazilJakoNahradnik($stavPrihlaseni)));
        $ucastnikTemplate->assign('cssTridaDisplaySledujici', $this->cssZobrazitKdyz(StavPrihlaseni::prihlasenJakoSledujici($stavPrihlaseni)));

        $zmenaPrihlaseni = $aktivita->dejPrezenci()->posledniZmenaPrihlaseni($ucastnik);
        $ucastnikTemplate->assign('casPosledniZmenyPrihlaseni', $zmenaPrihlaseni ? $zmenaPrihlaseni->casZmenyProJs() : '');
        $ucastnikTemplate->assign('stavPrihlaseni', $zmenaPrihlaseni ? $zmenaPrihlaseni->typPrezenceProJs() : '');
        $ucastnikTemplate->assign('idPoslednihoLogu', $zmenaPrihlaseni ? $zmenaPrihlaseni->idLogu() : 0);
        $ucastnikTemplate->assign('email', $ucastnik->mail());

        $ucastnikTemplate->parse('ucastnik');
        return $ucastnikTemplate->text('ucastnik');
    }

    private function cssZobrazitKdyz(bool $zobrazit): string
    {
        return $zobrazit
            ? ''
            : 'display-none';
    }

    private function jeToNaPosledniChvili(Uzivatel $ucastnik, Aktivita $aktivita): bool
    {
        $prihlasenOd               = $aktivita->prihlasenOd($ucastnik);
        $odKdyJeToNaPosledniChvili = $this->odKdyJeToNaPosledniChvili($aktivita);
        return $prihlasenOd && $odKdyJeToNaPosledniChvili && $prihlasenOd >= $odKdyJeToNaPosledniChvili;
    }

    private function odKdyJeToNaPosledniChvili(Aktivita $aktivita): ?\DateTimeInterface
    {
        $zacatek = $aktivita->zacatek();
        if (!$zacatek) {
            return null;
        }
        return (clone $zacatek)->modify('-' . $this->naPosledniChviliXMinutPredZacatkem . ' minutes');
    }

    private function dejOnlinePrezenceUcastnikTemplate(): XTemplate
    {
        if ($this->onlinePrezenceUcastnikTemplate === null) {
            $this->onlinePrezenceUcastnikTemplate = new XTemplate(__DIR__ . '/templates/online-prezence-ucastnik.xtpl');
        }
        return $this->onlinePrezenceUcastnikTemplate;
    }
}
