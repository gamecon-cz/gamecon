<?php

use Gamecon\Aktivita\Aktivita;

/**
 * Prezenční listina aktivity.
 */
class AktivitaPrezence
{

    private $aktivita;

    public function __construct(Aktivita $aktivita) {
        $this->aktivita = $aktivita;
    }

    /**
     * Uloží prezenci do databáze.
     * @param Uzivatel[] $dorazili uživatelé, kteří se nakonec aktivity zúčastnili
     */
    public function uloz(array $dorazili) {
        $doraziliIds = []; // id všech co dorazili (kvůli kontrole přítomnosti)

        // TODO kontrola, jestli prezence smí být uložena (např. jestli už nebyla uložena dřív)

        foreach ($dorazili as $dorazil) {
            $this->ulozDorazivsiho($dorazil);
            $doraziliIds[$dorazil->id()] = true;
        }
        foreach ($this->aktivita->prihlaseni() as $uzivatel) {
            if (!isset($doraziliIds[$uzivatel->id()])) {
                $this->ulozNedorazivsiho($uzivatel);
            }
        }
    }

    public function ulozDorazivsiho(Uzivatel $dorazil) {
        // TODO kontrola, jestli prezence smí být uložena (např. jestli už nebyla uložena dřív)

        if ($this->aktivita->prihlasen($dorazil)) {
            dbInsertUpdate('akce_prihlaseni', [
                'id_uzivatele' => $dorazil->id(),
                'id_akce' => $this->aktivita->id(),
                'id_stavu_prihlaseni' => Aktivita::PRIHLASEN_A_DORAZIL,
            ]);
        } else {
            $this->aktivita->odhlasZNahradnickychSlotu($dorazil);
            dbInsert('akce_prihlaseni', [
                'id_uzivatele' => $dorazil->id(),
                'id_akce' => $this->aktivita->id(),
                'id_stavu_prihlaseni' => Aktivita::DORAZIL_JAKO_NAHRADNIK,
            ]);
            $this->log($dorazil, 'prihlaseni_nahradnik');
        }
    }

    public function zrusDorazeni(Uzivatel $dorazil): bool {
        // TODO kontrola, jestli prezence smí být uložena (např. jestli už nebyla uložena dřív)

        if ($this->aktivita->dorazilJakoNahradnik($dorazil)) {
            /* Návštěvník přidaný k aktivitě přes online prezenci se přidá jako náhradník a obratem potvrdí jeho přítomnost - přestože to aktivita sama vlastně nedovoluje. Když ho z aktivity zas ruší, tak ho ale nemůžeme zařadit do fronty jako náhradníka, protože to aktivita vlastně nedovoluje (a my to popravdě ani nechceme, když ho odškrtli při samotné online prezenci) */
            if ($this->aktivita->prihlasovatelnaNahradnikum()) {
                $this->aktivita->prihlasNahradnika($dorazil);
            }
            dbDelete('akce_prihlaseni', [
                'id_uzivatele' => $dorazil->id(),
                'id_akce' => $this->aktivita->id(),
            ]);
            $this->log($dorazil, 'zruseni_prihlaseni_nahradnik');
            return true;
        }
        if ($this->aktivita->dorazilJakoPredemPrihlaseny($dorazil)) {
            dbUpdate('akce_prihlaseni',
                ['id_stavu_prihlaseni' => Aktivita::PRIHLASEN], // vratime ho zpet jako "jen prihlaseneho"
                ['id_uzivatele' => $dorazil->id(), 'id_akce' => $this->aktivita->id()]
            );
            return true;
        }
        // else neni co menit
        return false;
    }

    public function ulozNedorazivsiho(Uzivatel $nedorazil) {
        // TODO kontrola, jestli prezence smí být uložena (např. jestli už nebyla uložena dřív)

        dbDelete('akce_prihlaseni', [
            'id_uzivatele' => $nedorazil->id(),
            'id_akce' => $this->aktivita->id(),
        ]);
        dbInsert('akce_prihlaseni_spec', [
            'id_uzivatele' => $nedorazil->id(),
            'id_akce' => $this->aktivita->id(),
            'id_stavu_prihlaseni' => Aktivita::PRIHLASEN_ALE_NEDORAZIL,
        ]);
        $this->log($nedorazil, 'nedostaveni_se');
        $this->posliMailNedorazivsimu($nedorazil);
    }

    /////////////
    // private //
    /////////////

    /**
     * Zapíše do logu přihlášení kombinaci aktivita + uživatel + zpráva.
     */
    private function log(Uzivatel $u, $zprava) {
        dbInsert('akce_prihlaseni_log', [
            'id_uzivatele' => $u->id(),
            'id_akce' => $this->aktivita->id(),
            'typ' => $zprava,
        ]);
    }

    /**
     * Pošle uživateli výchovný mail, že se nedostavil na aktivitu, a že by se
     * měl radši odhlašovat předem.
     */
    private function posliMailNedorazivsimu(Uzivatel $u) {
        if (!GC_BEZI || !$this->aktivita->typ()->posilatMailyNedorazivsim()) {
            return;
        }

        (new GcMail)
            ->adresat($u->mail())
            ->predmet('Nedostavení se na aktivitu')
            ->text(hlaskaMail('nedostaveniSeNaAktivituMail', $u))
            ->odeslat();
    }

}
