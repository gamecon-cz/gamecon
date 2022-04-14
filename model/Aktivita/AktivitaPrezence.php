<?php

namespace Gamecon\Aktivita;

/**
 * Prezenční listina aktivity.
 */
class AktivitaPrezence
{

    const PRIHLASENI = 'prihlaseni';
    const ODHLASENI = 'odhlaseni';
    const NEDOSTAVENI_SE = 'nedostaveni_se';
    const ODHLASENI_HROMADNE = 'odhlaseni_hromadne';
    const DORAZIL_JAKO_NAHRADNIK = 'prihlaseni_nahradnik'; // TODO zmenit enum v databázi a hodnotu téhle konstanty, aby to odpovídalo tomu, co logujeme
    const ZRUSENI_PRIHLASENI_NAHRADNIK = 'zruseni_prihlaseni_nahradnik';
    const PRIHLASENI_SLEDUJICI = 'prihlaseni_watchlist'; // TODO zmenit enum v databázi a hodnotu téhle konstanty, aby to odpovídalo více používanému českému názvu
    const ODHLASENI_SLEDUJICI = 'odhlaseni_watchlist'; // TODO zmenit enum v databázi a hodnotu téhle konstanty, aby to odpovídalo více používanému českému názvu

    /** @var Aktivita */
    private $aktivita;

    public function __construct(Aktivita $aktivita) {
        $this->aktivita = $aktivita;
    }

    /**
     * Uloží prezenci do databáze.
     * @param \Uzivatel[] $dorazili uživatelé, kteří se nakonec aktivity zúčastnili
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

    public function ulozDorazivsiho(\Uzivatel $dorazil) {
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
            $this->zalogujZeDorazilJakoNahradnik($dorazil);
        }
    }

    public function zrusZeDorazil(\Uzivatel $dorazil): bool {
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
            $this->zalogujZeZrusilPrihlaseniJakoNahradik($dorazil);
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

    public function zalogujZeSePrihlasil(\Uzivatel $prihlaseny) {
        $this->log($prihlaseny, self::PRIHLASENI);
    }

    private function log(\Uzivatel $u, $zprava) {
        dbInsert('akce_prihlaseni_log', [
            'id_uzivatele' => $u->id(),
            'id_akce' => $this->aktivita->id(),
            'typ' => $zprava,
        ]);
    }

    public function zalogujZeSeOdhlasil(\Uzivatel $odhlaseny) {
        $this->log($odhlaseny, self::ODHLASENI);
    }

    private function zalogujZeZeNedostavil(\Uzivatel $nedorazil) {
        $this->log($nedorazil, self::NEDOSTAVENI_SE);
    }

    public function zalogujZeBylHromadneOdhlasen(\Uzivatel $hromadneOdhlasen) {
        $this->log($hromadneOdhlasen, self::ODHLASENI_HROMADNE);
    }

    private function zalogujZeDorazilJakoNahradnik(\Uzivatel $dorazilNahradnik) {
        $this->log($dorazilNahradnik, self::DORAZIL_JAKO_NAHRADNIK);
    }

    public function zalogujZeSePrihlasilJakoSledujici(\Uzivatel $prihlasenySledujici) {
        $this->log($prihlasenySledujici, self::PRIHLASENI_SLEDUJICI);
    }

    public function zalogujZeZrusilPrihlaseniJakoNahradik(\Uzivatel $prihlasenySledujici) {
        $this->log($prihlasenySledujici, self::ZRUSENI_PRIHLASENI_NAHRADNIK);
    }

    public function zalogujZeSeOdhlasilJakoSledujici(\Uzivatel $odhlasenySledujici) {
        dbQuery(
            "INSERT INTO akce_prihlaseni_log SET id_uzivatele=$1, id_akce=$2, typ=$3",
            [$odhlasenySledujici->id(), $this->aktivita->id(), self::ODHLASENI_SLEDUJICI]
        );
    }

    public function ulozNedorazivsiho(\Uzivatel $nedorazil) {
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
        $this->zalogujZeZeNedostavil($nedorazil);
        $this->posliMailNedorazivsimu($nedorazil);
    }

    /**
     * Pošle uživateli výchovný mail, že se nedostavil na aktivitu, a že by se
     * měl radši odhlašovat předem.
     */
    private function posliMailNedorazivsimu(\Uzivatel $u) {
        if (!GC_BEZI || !$this->aktivita->typ()->posilatMailyNedorazivsim()) {
            return;
        }

        (new \GcMail)
            ->adresat($u->mail())
            ->predmet('Nedostavení se na aktivitu')
            ->text(hlaskaMail('nedostaveniSeNaAktivituMail', $u))
            ->odeslat();
    }

    public function prihlasenOd(\Uzivatel $uzivatel): ?\DateTimeImmutable {
        $akceACasy = dbFetchAll(<<<SQL
SELECT MAX(cas) AS kdy, typ
FROM akce_prihlaseni_log
WHERE id_akce = $1 AND id_uzivatele = $2
GROUP BY typ
ORDER BY kdy DESC
SQL,
            [$this->aktivita->id(), $uzivatel->id()]
        );
        if (!$akceACasy) {
            return null;
        }
        $posledniAkce = reset($akceACasy);
        if ($posledniAkce['typ'] !== self::PRIHLASENI) {
            return null;
        }
        return new \DateTimeImmutable($posledniAkce['kdy']);
    }
}
