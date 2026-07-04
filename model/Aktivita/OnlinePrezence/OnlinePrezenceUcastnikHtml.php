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
        Uzivatel $vypravec,
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

        if ($ucastnik->telefon() && $this->smiZobrazitTelefon($vypravec)) {
            $ucastnikTemplate->assign('telefonHtml', $ucastnik->telefon(true));
            $ucastnikTemplate->assign('telefonRaw', $ucastnik->telefon(false));
            $ucastnikTemplate->parse('ucastnik.telefon');
        }

        if ($this->jeToNaPosledniChvili($ucastnik, $aktivita)) {
            $ucastnikTemplate->assign('minutNaPosledniChvili', $this->naPosledniChviliXMinutPredZacatkem);
            $ucastnikTemplate->parse('ucastnik.prihlasenNaPosledniChvili');
        }

        if (($vek = $ucastnik->vekKDatu($aktivita->zacatek() ?? $this->systemoveNastaveni->spocitanyKonecLetosnihoGameconu())) < 18) {
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

    /**
     * Rozhoduje podle role aktuálního diváka prezence ($vypravec), ne podle
     * zobrazovaného účastníka: organizátor vidí telefony vždy (kvůli
     * koordinaci), ostatní (typicky uživatelé s právem ADMINISTRACE_PREZENCE
     * bez organizátorské role) jen po dobu běhu GameConu – ochrana osobních
     * údajů, aby se čísla nedala hromadně vytáhnout před akcí ani po ní.
     */
    private function smiZobrazitTelefon(Uzivatel $vypravec): bool
    {
        return $vypravec->jeOrganizator()
            || $this->gcBeziPodleInjektovanehoCasu();
    }

    /**
     * Obdoba {@see SystemoveNastaveni::gcBezi()}, ale porovnává proti
     * injektovanému času ($this->ted()) místo reálného time(). Zbytek prezence
     * (editovatelnost, checkboxy) se řídí injektovaným časem, takže i skrývání
     * telefonů musí, jinak by v testech / preview s nasimulovaným časem bylo UI
     * nekonzistentní. Hranice jsou inkluzivní stejně jako v mezi() – v okamžiku
     * GC_BEZI_OD i GC_BEZI_DO je GameCon považován za běžící.
     */
    private function gcBeziPodleInjektovanehoCasu(): bool
    {
        $ted = $this->systemoveNastaveni->ted()->getTimestamp();
        return $this->systemoveNastaveni->gcBeziOd()->getTimestamp() <= $ted
            && $ted <= $this->systemoveNastaveni->gcBeziDo()->getTimestamp();
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
