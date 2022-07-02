<?php declare(strict_types=1);

namespace Gamecon\Aktivita;

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
    /** @var ZmenaPrihlaseni[]|null[] */
    private $posledniZmenaPrihlaseni = [];

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
        if ($this->aktivita->dorazilJakoCokoliv($dorazil)) {
            return; // už máme hotovo
        }
        if ($this->aktivita->prihlasen($dorazil)) {
            dbInsertUpdate('akce_prihlaseni', [
                'id_uzivatele' => $dorazil->id(),
                'id_akce' => $this->aktivita->id(),
                'id_stavu_prihlaseni' => Aktivita::PRIHLASEN_A_DORAZIL,
            ]);
            $this->zalogujZeDorazil($dorazil);
        } else {
            $this->aktivita->odhlasZeSledovaniAktivitVeStejnemCase($dorazil);
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

    private function log(\Uzivatel $u, string $zprava) {
        dbInsert('akce_prihlaseni_log', [
            'id_uzivatele' => $u->id(),
            'id_akce' => $this->aktivita->id(),
            'typ' => $zprava,
        ]);
        RazitkoPosledniZmenyPrihlaseni::smazRazitkaPoslednichZmen($this->aktivita, $this->filesystem);
        unset($this->posledniZmenaPrihlaseni[$u->id()]);
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
        $posledniZmenaPrihlaseni = $this->posledniZmenaPrihlaseni($uzivatel);
        if (!$posledniZmenaPrihlaseni || $posledniZmenaPrihlaseni->typPrezence() !== AktivitaPrezenceTyp::PRIHLASENI) {
            return null;
        }
        return $posledniZmenaPrihlaseni->casZmeny();
    }

    public function posledniZmenaPrihlaseni(\Uzivatel $ucastnik): ?ZmenaPrihlaseni {
        if (!array_key_exists($ucastnik->id(), $this->posledniZmenaPrihlaseni)) {
            $this->posledniZmenaPrihlaseni[$ucastnik->id()] = self::posledniZmenaPrihlaseniAktivit($ucastnik, [$this->aktivita]);
        }
        return $this->posledniZmenaPrihlaseni[$ucastnik->id()];
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
                    WHERE akce_prihlaseni_spec.id_akce = $1
                    AND akce_prihlaseni_spec.id_stavu_prihlaseni = $2',
                    [$this->aktivita->id(), $this->aktivita::SLEDUJICI]
                )
            );
        }
        return $this->seznamSledujicich;
    }

    /**
     * @param string[][][] $idsPoslednichZnamychLoguUcastniku
     * @return PosledniZmenyPrihlaseni
     */
    public static function dejPosledniZmenyPrezence(array $idsPoslednichZnamychLoguUcastniku): PosledniZmenyPrihlaseni {
        $nejnovejsiZmenyPrihlaseni = new PosledniZmenyPrihlaseni();
        foreach (self::dejDataPoslednichZmen($idsPoslednichZnamychLoguUcastniku) as $zmena) {
            $zmenaPrihlaseni = ZmenaPrihlaseni::vytvorZDatDatabaze(
                (int)$zmena['id_uzivatele'],
                (int)$zmena['id_akce'],
                (int)$zmena['id_log'],
                new \DateTimeImmutable($zmena['kdy']),
                $zmena['typ']
            );
            $nejnovejsiZmenyPrihlaseni->addPosledniZmenaPrihlaseni($zmenaPrihlaseni);
        }
        return $nejnovejsiZmenyPrihlaseni;
    }

    /**
     * @param string[][][] $idsPoslednichZnamychLoguUcastniku Například {"4387":[{"idUzivatele":"102","idPoslednihoLogu":"66329"}],"4389":[{"idUzivatele":"295","idPoslednihoLogu":"66382"},{"idUzivatele":"73","idPoslednihoLogu":"66385"}]}
     * Formát viz online-prezence-posledni-zname-zmeny-prihlaseni.js
     * @return array
     * @throws \DbException
     */
    private static function dejDataPoslednichZmen(array $idsPoslednichZnamychLoguUcastniku): array {
        if (!$idsPoslednichZnamychLoguUcastniku) {
            return [];
        }

        $whereOrArray = [];
        $sqlQueryParametry = [];
        $indexSqlParametru = 0;
        foreach ($idsPoslednichZnamychLoguUcastniku as $idAktivity => $uzivateleALogy) {
            $idAktivity = (int)$idAktivity;
            $idZnamychUcastnikuAktivity = [];
            $idPoslednihZnamychLogu = [];
            foreach ($uzivateleALogy as ['idUzivatele' => $idUzivatele, 'idPoslednihoLogu' => $idPoslednihoZnamehoLogu]) {
                $idUzivatele = (int)$idUzivatele;
                $idPoslednihoZnamehoLogu = (int)$idPoslednihoZnamehoLogu;

                $whereOrArray[] = "(id_akce = $idAktivity AND id_uzivatele = $idUzivatele AND id_log > $idPoslednihoZnamehoLogu)";

                $idZnamychUcastnikuAktivity[] = $idUzivatele;
                $idPoslednihZnamychLogu[] = $idPoslednihoZnamehoLogu;
            }
            $idNejstarsihoPoslednihoZnamehoLogu = max(array_merge($idPoslednihZnamychLogu, [0]/* pro případ že aktivita byla prázdná */));
            $whereOrArray[] = "(id_akce = {$idAktivity} AND id_uzivatele NOT IN ($$indexSqlParametru) AND id_log > $idNejstarsihoPoslednihoZnamehoLogu)";
            $sqlQueryParametry[$indexSqlParametru] = $idZnamychUcastnikuAktivity;
            $indexSqlParametru++;
        }
        $where = implode(' OR ', $whereOrArray);

        return dbFetchAll(<<<SQL
SELECT akce_prihlaseni_log.id_akce, akce_prihlaseni_log.id_uzivatele, akce_prihlaseni_log.typ, akce_prihlaseni_log.kdy, akce_prihlaseni_log.id_log
FROM (
    SELECT akce_prihlaseni_log.id_akce, akce_prihlaseni_log.id_uzivatele, MAX(akce_prihlaseni_log.id_log) AS id_posledniho_logu
    FROM akce_prihlaseni_log
    WHERE {$where}
    GROUP BY id_akce, id_uzivatele
) AS nejnovejsi
INNER JOIN akce_prihlaseni_log
    ON nejnovejsi.id_akce = akce_prihlaseni_log.id_akce
        AND nejnovejsi.id_uzivatele = akce_prihlaseni_log.id_uzivatele
        AND nejnovejsi.id_posledniho_logu = akce_prihlaseni_log.id_log
GROUP BY akce_prihlaseni_log.id_akce, akce_prihlaseni_log.id_uzivatele
SQL
            , $sqlQueryParametry
        );
    }

    /**
     * @param \Uzivatel|null $ucastnik
     * @param Aktivita[] $aktivity
     * @return null|ZmenaPrihlaseni
     * @throws \Exception
     */
    public static function posledniZmenaPrihlaseniAktivit(?\Uzivatel $ucastnik, array $aktivity): ?ZmenaPrihlaseni {
        if (count($aktivity) === 0) {
            return null;
        }
        $posledniZmena = dbOneLine(<<<SQL
SELECT akce_prihlaseni_log.id_uzivatele, akce_prihlaseni_log.id_akce, akce_prihlaseni_log.id_log, akce_prihlaseni_log.typ, akce_prihlaseni_log.kdy
FROM (
    SELECT id_akce, id_uzivatele, MAX(id_log) AS id_posledniho_logu
    FROM akce_prihlaseni_log
    WHERE id_akce IN ($1) AND IF($2 IS NULL, TRUE, id_uzivatele = $2)
    GROUP BY id_akce, id_uzivatele
    LIMIT 1
) AS nejnovejsi
INNER JOIN akce_prihlaseni_log
    ON nejnovejsi.id_uzivatele = akce_prihlaseni_log.id_uzivatele
    AND nejnovejsi.id_akce = akce_prihlaseni_log.id_akce
    AND nejnovejsi.id_posledniho_logu = akce_prihlaseni_log.id_log
SQL,
            [
                array_map(static function (Aktivita $aktivita) {
                    return $aktivita->id();
                }, $aktivity),
                $ucastnik ? $ucastnik->id() : null,
            ]
        );
        if (!$posledniZmena['id_uzivatele']) {
            return null;
        }
        return new ZmenaPrihlaseni(
            (int)$posledniZmena['id_uzivatele'],
            (int)$posledniZmena['id_akce'],
            (int)$posledniZmena['id_log'],
            new \DateTimeImmutable($posledniZmena['kdy']),
            $posledniZmena['typ']
        );
    }

}
