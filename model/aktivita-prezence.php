<?php

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
        $prihlaseni = [];  // přihlášení kteří dorazili
        $nahradnici = [];  // náhradníci
        $nedorazili = [];  // přihlášení kteří nedorazili
        $doraziliIds = []; // id všech co dorazili (kvůli kontrole přítomnosti)

        // TODO kontrola, jestli prezence smí být uložena (např. jestli už nebyla uložena dřív)

        // určení skupin kdo dorazil a kdo ne
        foreach ($dorazili as $u) {
            if ($this->aktivita->prihlasen($u)) {
                $prihlaseni[] = $u;
            } else {
                $nahradnici[] = $u;
            }
            $doraziliIds[$u->id()] = true;
        }
        foreach ($this->aktivita->prihlaseni() as $u) {
            if (isset($doraziliIds[$u->id()])) {
                continue;
            }
            $nedorazili[] = $u;
        }

        // úprava stavu přihlášení podle toho do jaké skupiny spadá
        foreach ($prihlaseni as $u) {
            dbInsertUpdate('akce_prihlaseni', [
                'id_uzivatele' => $u->id(),
                'id_akce' => $this->aktivita->id(),
                'id_stavu_prihlaseni' => Aktivita::PRIHLASEN_A_DORAZIL,
            ]);
        }

        foreach ($nahradnici as $u) {
            $this->aktivita->odhlasZNahradnickychSlotu($u);
            dbInsert('akce_prihlaseni', [
                'id_uzivatele' => $u->id(),
                'id_akce' => $this->aktivita->id(),
                'id_stavu_prihlaseni' => Aktivita::DORAZIL_JAKO_NAHRADNIK,
            ]);
            $this->log($u, 'prihlaseni_nahradnik');
        }

        foreach ($nedorazili as $u) {
            dbDelete('akce_prihlaseni', [
                'id_uzivatele' => $u->id(),
                'id_akce' => $this->aktivita->id(),
            ]);
            dbInsert('akce_prihlaseni_spec', [
                'id_uzivatele' => $u->id(),
                'id_akce' => $this->aktivita->id(),
                'id_stavu_prihlaseni' => Aktivita::PRIHLASEN_ALE_NEDORAZIL,
            ]);
            $this->log($u, 'nedostaveni_se');
            $this->posliMailNedorazivsimu($u);
        }
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
