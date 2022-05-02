<?php

namespace Gamecon\Aktivita;

use Gamecon\Cas\DateTimeCz;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Prezenční listina aktivity.
 */
class AktivitaPrezence
{

    /** @var Aktivita */
    private $aktivita;
    /** @var void|\Uzivatel[] */
    private $seznamSledujicich;
    /** @var Filesystem */
    private $filesystem;

    public function __construct(
        Aktivita   $aktivita,
        Filesystem $filesystem
    ) {
        $this->aktivita = $aktivita;
        $this->filesystem = $filesystem;
    }

    /**
     * Uloží prezenci do databáze.
     * @param \Uzivatel[] $dorazili uživatelé, kteří se nakonec aktivity zúčastnili
     */
    public function uloz(array $dorazili) {
        $doraziliIds = []; // id všech co dorazili (kvůli kontrole přítomnosti)

        // TODO kontrola, jestli prezence smí být uložena (např. jestli už nebyla uložena dřív)

        foreach ($dorazili as $dorazil) {
            $this->ulozZeDorazil($dorazil);
            $doraziliIds[$dorazil->id()] = true;
        }
        foreach ($this->aktivita->prihlaseni() as $uzivatel) {
            if (!isset($doraziliIds[$uzivatel->id()])) {
                $this->ulozNedorazivsiho($uzivatel);
            }
        }
    }

    public function ulozZeDorazil(\Uzivatel $dorazil) {
        // TODO kontrola, jestli prezence smí být uložena (např. jestli už nebyla uložena dřív)

        if ($this->aktivita->prihlasen($dorazil)) {
            dbInsertUpdate('akce_prihlaseni', [
                'id_uzivatele' => $dorazil->id(),
                'id_akce' => $this->aktivita->id(),
                'id_stavu_prihlaseni' => Aktivita::PRIHLASEN_A_DORAZIL,
            ]);
            $this->zalogujZeDorazil($dorazil);
        } else {
            $this->aktivita->odhlasZeSledováníAktivitVeStejnemCase($dorazil);
            dbInsert('akce_prihlaseni', [
                'id_uzivatele' => $dorazil->id(),
                'id_akce' => $this->aktivita->id(),
                'id_stavu_prihlaseni' => Aktivita::DORAZIL_JAKO_NAHRADNIK,
            ]);
            $this->zalogujZeDorazilJakoNahradnik($dorazil);
        }
    }

    /**
     * @param \Uzivatel $dorazil
     * @return bool false pokud byl uživatel už zrušen a nic se tedy nezměnilo
     */
    public function zrusZeDorazil(\Uzivatel $nedorazil): bool {
        // TODO kontrola, jestli prezence smí být uložena (např. jestli už nebyla uložena dřív)

        if ($this->aktivita->dorazilJakoNahradnik($nedorazil)) {
            dbDelete('akce_prihlaseni', [
                'id_uzivatele' => $nedorazil->id(),
                'id_akce' => $this->aktivita->id(),
            ]);
            $this->zalogujZeZrusilPrihlaseniJakoNahradik($nedorazil);
            /* Návštěvník přidaný k aktivitě přes online prezenci se přidá jako náhradník a obratem potvrdí jeho přítomnost - přestože to aktivita sama vlastně nedovoluje. Když ho z aktivity zas ruší, tak ho ale nemůžeme zařadit do fronty jako náhradníka, protože to aktivita vlastně nedovoluje (a my to popravdě ani nechceme, když ho odškrtli při samotné online prezenci).
            PS: vlastně nechceme účastníka, kterého přidal vypravěč, "vracet" do stavu sledujícího, ale zatím to nechceme řešit. */
            if ($this->aktivita->prihlasovatelnaProSledujici()) {
                $this->aktivita->prihlasSledujiciho($nedorazil);
            }
            return true;
        }
        if ($this->aktivita->dorazilJakoPredemPrihlaseny($nedorazil)) {
            dbUpdate('akce_prihlaseni',
                ['id_stavu_prihlaseni' => Aktivita::PRIHLASEN], // vratime ho zpet jako "jen prihlaseneho"
                ['id_uzivatele' => $nedorazil->id(), 'id_akce' => $this->aktivita->id()]
            );
            $this->zalogujZeSePrihlasil($nedorazil);
            return true;
        }
        // else není co měnit, už je všude zrušený
        return false;
    }

    public function zalogujZeSePrihlasil(\Uzivatel $prihlaseny) {
        $this->log($prihlaseny, AktivitaPrezenceTyp::PRIHLASENI);
    }

    private function log(\Uzivatel $u, $zprava) {
        dbInsert('akce_prihlaseni_log', [
            'id_uzivatele' => $u->id(),
            'id_akce' => $this->aktivita->id(),
            'typ' => $zprava,
        ]);
        $this->smazRazitkaPoslednichZmen();
    }

    private function smazRazitkaPoslednichZmen() {
        if (defined('TESTING') && TESTING
            && defined('TEST_MAZAT_VSECHNA_RAZITKA_POSLEDNICH_ZMEN') && TEST_MAZAT_VSECHNA_RAZITKA_POSLEDNICH_ZMEN
        ) {
            /**
             * Při testování online prezence se vypisují i aktivity, které organizátor ve skutečnosti neorganizuje.
             * Proto musíme mazat všechna razítka, protože smazat je jen těm, kteří ji opravdu ogranizují, nestačí - neorganizujícímu testerovi by se nenačetly změny.
             */
            $this->filesystem->remove(self::dejAdresarProRazitkaPoslednichZmen());
            return;
        }
        foreach (self::dejAdresareProRazitkaPoslednichZmenProOrganizatory($this->aktivita) as $adresar) {
            $this->filesystem->remove($adresar);
        }
    }

    public function zalogujZeSeOdhlasil(\Uzivatel $odhlaseny) {
        $this->log($odhlaseny, AktivitaPrezenceTyp::ODHLASENI);
    }

    private function zalogujZeZeNedostavil(\Uzivatel $nedorazil) {
        $this->log($nedorazil, AktivitaPrezenceTyp::NEDOSTAVENI_SE);
    }

    public function zalogujZeBylHromadneOdhlasen(\Uzivatel $hromadneOdhlasen) {
        $this->log($hromadneOdhlasen, AktivitaPrezenceTyp::ODHLASENI_HROMADNE);
    }

    public function zalogujZeDorazil(\Uzivatel $dorazil) {
        $this->log($dorazil, AktivitaPrezenceTyp::DORAZIL);
    }

    private function zalogujZeDorazilJakoNahradnik(\Uzivatel $dorazilNahradnik) {
        $this->log($dorazilNahradnik, AktivitaPrezenceTyp::DORAZIL_JAKO_NAHRADNIK);
    }

    public function zalogujZeSePrihlasilJakoSledujici(\Uzivatel $prihlasenySledujici) {
        $this->log($prihlasenySledujici, AktivitaPrezenceTyp::PRIHLASENI_SLEDUJICI);
    }

    public function zalogujZeZrusilPrihlaseniJakoNahradik(\Uzivatel $prihlasenySledujici) {
        $this->log($prihlasenySledujici, AktivitaPrezenceTyp::NAHRADNIK_NEDORAZIL);
    }

    public function zalogujZeSeOdhlasilJakoSledujici(\Uzivatel $odhlasenySledujici) {
        $this->log($odhlasenySledujici, AktivitaPrezenceTyp::ODHLASENI_SLEDUJICI);
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
        $posledniZmenaStavuPrihlaseni = $this->dejPosledniZmenaStavuPrihlaseni($uzivatel);
        if ($posledniZmenaStavuPrihlaseni->stavPrihlaseni() !== AktivitaPrezenceTyp::PRIHLASENI) {
            return null;
        }
        return $posledniZmenaStavuPrihlaseni->casZmeny();
    }

    public function dejPosledniZmenaStavuPrihlaseni(\Uzivatel $ucastnik): ZmenaStavuPrihlaseni {
        return self::dejPosledniZmenaStavuPrihlaseniAktivit($ucastnik, [$this->aktivita]);
    }

    /**
     * Vrátí pole uživatelů, kteří jsou sledujícími na aktivitě
     * @return \Uzivatel[]
     */
    public function seznamSledujicich(): array {
        if (!isset($this->seznamSledujicich)) {
            $this->seznamSledujicich = \Uzivatel::zIds(
                dbOneCol('
                    SELECT GROUP_CONCAT(akce_prihlaseni_spec.id_uzivatele)
                    FROM akce_seznam a
                    LEFT JOIN akce_prihlaseni_spec ON akce_prihlaseni_spec.id_akce = a.id_akce
                    WHERE akce_prihlaseni_spec.id_akce = ' . $this->aktivita->id() . '
                    AND akce_prihlaseni_spec.id_stavu_prihlaseni = ' . $this->aktivita::SLEDUJICI
                )
            );
        }
        return $this->seznamSledujicich;
    }

    /**
     * Je alespoń jeden účastník označen jako že dorazil, dorazil jako náhradník, nebo byl přihlášen ale nedorazil?
     * @return bool
     */
    public function jePrezenceUzavrena(): bool {
        return $this->aktivita->uzavrena();
    }

    /**
     * @param PosledniZmenyStavuPrihlaseni $posledniZnameZmenyStavuPrihlaseni
     * @return PosledniZmenyStavuPrihlaseni
     */
    public static function dejPosledniZmeny(PosledniZmenyStavuPrihlaseni $posledniZnameZmenyStavuPrihlaseni): PosledniZmenyStavuPrihlaseni {
        $indexParametru = 0;
        $where = 'akce_prihlaseni_log.id_akce = $' . $indexParametru;
        $sqlQueryParametry = [$indexParametru => $posledniZnameZmenyStavuPrihlaseni->getIdAktivity()];

        $novejsiNezZnameZmenyStavuSql = [];
        foreach ($posledniZnameZmenyStavuPrihlaseni->zmenyStavuPrihlaseni() as $zmenaStavuPrihlaseni) {
            $identifikatoryAktivitySql = [];

            $casZmenyStavu = $zmenaStavuPrihlaseni->casZmeny();
            if ($casZmenyStavu) {
                $indexParametru++;
                $novejsiNeboJinySql = 'akce_prihlaseni_log.cas > $' . $indexParametru; // novejsi
                $sqlQueryParametry[$indexParametru] = $casZmenyStavu->format(DateTimeCz::FORMAT_DB);

                $indexParametru++;
                $jinyVeStejnyCasSql = 'akce_prihlaseni_log.cas = $' . $indexParametru; // nebo ve stejny cas...
                $sqlQueryParametry[$indexParametru] = $casZmenyStavu->format(DateTimeCz::FORMAT_DB);

                $jinyTypNeboUcastnikSql = [];
                $indexParametru++;
                // ...ale odlisny stav (abychom nereagovali na tu samou zmenu vicekrat)...
                $jinyTypNeboUcastnikSql[] = 'akce_prihlaseni_log.typ != $' . $indexParametru;
                $sqlQueryParametry[$indexParametru] = $zmenaStavuPrihlaseni->stavPrihlaseni();
                $indexParametru++;
                // ...nebo je to jiny ucastnik
                $jinyTypNeboUcastnikSql[] = 'akce_prihlaseni_log.id_uzivatele != $' . $indexParametru;
                $sqlQueryParametry[$indexParametru] = $zmenaStavuPrihlaseni->idUzivatele();

                $jinyVeStejnyCasSql .= ' AND (' . implode(' OR ', $jinyTypNeboUcastnikSql) . ')';

                $novejsiNeboJinySql .= ' OR (' . $jinyVeStejnyCasSql . ')';

                $identifikatoryAktivitySql[] = $novejsiNeboJinySql;
            }

            $novejsiNezZnameZmenyStavuSql[] = '(' . implode(' AND ', $identifikatoryAktivitySql) . ')';
        }
        if ($novejsiNezZnameZmenyStavuSql) {
            $where .= ' AND (' . implode(' OR ', $novejsiNezZnameZmenyStavuSql) . ')';
        }
        /* For example:
        SELECT akce_prihlaseni_log.id_akce, akce_prihlaseni_log.id_uzivatele, akce_prihlaseni_log.typ, akce_prihlaseni_log.cas
        FROM (SELECT akce_prihlaseni_log.id_akce, akce_prihlaseni_log.id_uzivatele, MAX(akce_prihlaseni_log.id_log) AS posledni_id
            FROM akce_prihlaseni_log
            LEFT JOIN akce_prihlaseni on akce_prihlaseni_log.id_akce = akce_prihlaseni.id_akce
            WHERE akce_prihlaseni_log.id_akce = 4057
            AND (
                (akce_prihlaseni_log.cas > '2022-04-26 11:48:54'
                OR (akce_prihlaseni_log.cas = '2022-04-26 11:48:54'
                    AND (akce_prihlaseni_log.typ != 'prihlaseni_nahradnik' OR akce_prihlaseni_log.id_uzivatele != 517))
            ))
        GROUP BY id_akce, id_uzivatele) AS nejnovejsi
        INNER JOIN akce_prihlaseni_log
            ON nejnovejsi.id_akce = akce_prihlaseni_log.id_akce
            AND nejnovejsi.id_uzivatele = akce_prihlaseni_log.id_uzivatele
            AND nejnovejsi.posledni_id = akce_prihlaseni_log.id_log
        GROUP BY akce_prihlaseni_log.id_akce, akce_prihlaseni_log.id_uzivatele;
         */

        $zmeny = dbFetchAll(<<<SQL
SELECT akce_prihlaseni_log.id_akce, akce_prihlaseni_log.id_uzivatele, akce_prihlaseni_log.typ, akce_prihlaseni_log.cas
FROM (
    SELECT akce_prihlaseni_log.id_akce, akce_prihlaseni_log.id_uzivatele, MAX(akce_prihlaseni_log.id_log) AS posledni_id
    FROM akce_prihlaseni_log
    LEFT JOIN akce_prihlaseni on akce_prihlaseni_log.id_akce = akce_prihlaseni.id_akce
    WHERE $where
    GROUP BY id_akce, id_uzivatele
) AS nejnovejsi
INNER JOIN akce_prihlaseni_log
    ON nejnovejsi.id_akce = akce_prihlaseni_log.id_akce
    AND nejnovejsi.id_uzivatele = akce_prihlaseni_log.id_uzivatele
    AND nejnovejsi.posledni_id = akce_prihlaseni_log.id_log
GROUP BY akce_prihlaseni_log.id_akce, akce_prihlaseni_log.id_uzivatele
SQL
            , $sqlQueryParametry
        );

        $nejnovejsiZmenyStavuPrihlaseni = new PosledniZmenyStavuPrihlaseni($posledniZnameZmenyStavuPrihlaseni->getIdAktivity());
        foreach ($zmeny as $zmena) {
            $zmenaStavuPrihlaseni = ZmenaStavuPrihlaseni::vytvorZDatDatabaze(
                (int)$zmena['id_uzivatele'],
                (int)$zmena['id_akce'],
                new \DateTimeImmutable($zmena['cas']),
                $zmena['typ']
            );
            $nejnovejsiZmenyStavuPrihlaseni->addPosledniZmenaStavuPrihlaseni($zmenaStavuPrihlaseni);
        }
        return $nejnovejsiZmenyStavuPrihlaseni;
    }

    /**
     * @param \Uzivatel|null $ucastnik
     * @param Aktivita[] $aktivity
     * @return ZmenaStavuPrihlaseni
     * @throws \Exception
     */
    public static function dejPosledniZmenaStavuPrihlaseniAktivit(?\Uzivatel $ucastnik, array $aktivity): ?ZmenaStavuPrihlaseni {
        $posledniZmena = dbOneLine(<<<SQL
SELECT nejnovejsi.id_uzivatele, nejnovejsi.kdy, nejnovejsi.id_akce, akce_prihlaseni_log.typ
FROM (
    SELECT MAX(cas) AS kdy, id_akce, id_uzivatele
    FROM akce_prihlaseni_log
    WHERE id_akce IN ($1) AND IF($2 IS NULL, TRUE, id_uzivatele = $2)
    GROUP BY id_akce, id_uzivatele
    HAVING kdy = (SELECT MAX(cas) AS kdy FROM akce_prihlaseni_log WHERE id_akce IN ($1) AND IF($2 IS NULL, TRUE, id_uzivatele = $2))
    LIMIT 1
) AS nejnovejsi
INNER JOIN akce_prihlaseni_log
    ON nejnovejsi.id_uzivatele = akce_prihlaseni_log.id_uzivatele
    AND nejnovejsi.id_akce = akce_prihlaseni_log.id_akce
    AND nejnovejsi.kdy = akce_prihlaseni_log.cas
GROUP BY akce_prihlaseni_log.id_akce, akce_prihlaseni_log.id_uzivatele
SQL,
            [
                array_map(static function (Aktivita $aktivita) {
                    return $aktivita->id();
                }, $aktivity),
                $ucastnik ? $ucastnik->id() : null,
            ]
        );
        $idUzivatelePosledniZmeny = $posledniZmena['id_uzivatele'] ?? ($ucastnik ? $ucastnik->id() : null);
        if (!$idUzivatelePosledniZmeny) {
            return null;
        }
        return new ZmenaStavuPrihlaseni(
            $idUzivatelePosledniZmeny,
            $posledniZmena['id_akce'] ?? null,
            $posledniZmena
                ? new \DateTimeImmutable($posledniZmena['kdy'])
                : null,
            $posledniZmena['typ'] ?? null
        );
    }

    /**
     * @param Aktivita $aktivita
     * @return string[]
     */
    public static function dejAdresareProRazitkaPoslednichZmenProOrganizatory(Aktivita $aktivita): array {
        $adresare = [];
        foreach ($aktivita->organizatori() as $vypravec) {
            $adresare[] = self::dejAdresarProRazitkoPosledniZmeny($vypravec, $aktivita);
        }
        return array_unique($adresare);
    }

    public static function dejAdresarProRazitkoPosledniZmeny(\Uzivatel $vypravec, Aktivita $aktivita): string {
        return self::dejAdresarProRazitkaPosledniZmeny($aktivita) . '/vypravec-' . $vypravec->id();
    }

    private static function dejAdresarProRazitkaPosledniZmeny(Aktivita $aktivita): string {
        return self::dejAdresarProRazitkaPoslednichZmen() . '/aktivita-' . $aktivita->id();
    }

    private static function dejAdresarProRazitkaPoslednichZmen(): string {
        return ADMIN_STAMPS . '/zmeny';
    }

}
