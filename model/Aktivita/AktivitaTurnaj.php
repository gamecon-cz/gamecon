<?php

namespace Gamecon\Aktivita;

/**
 * Doménový objekt pro turnaj.
 * Poskytuje business logiku pro turnajové funkcionality.
 */
class AktivitaTurnaj
{
    private function __construct(
        private readonly int $idTurnaje,
    ) {}

    // ====== FACTORY ======

    /** Najde turnaj podle ID. Vrátí null pokud turnaj neexistuje. */
    public static function najdiPodleId(?int $idTurnaje): ?self
    {
        if ($idTurnaje === null) {
            return null;
        }

        $turnaj = dbFetchRow(
            'SELECT id_turnaje FROM turnaje WHERE id_turnaje = $0',
            [$idTurnaje],
        );

        if (!$turnaj) {
            return null;
        }

        return new self($idTurnaje);
    }

    // ====== INSTANCE GETTERY ======

    public function getId(): int
    {
        return $this->idTurnaje;
    }

    // ====== INSTANCE OPERACE ======

    /**
     * Vrátí aktivity turnaje seskupené podle kola.
     * Vrátí prázdné pole pokud turnaj nemá žádné aktivity.
     *
     * @return array<int, int[]> Asociativní pole [kolo => [id_akce, ...]]
     */
    public function idAktivitProKola(): array
    {
        $aktivitaVTurnaji = dbFetchAll(
            'SELECT id_akce, turnaj_kolo FROM akce_seznam WHERE id_turnaje = $0 ORDER BY turnaj_kolo',
            [$this->getId()],
        );

        $aktivitaProKolo = [];
        foreach ($aktivitaVTurnaji as $riadok) {
            $kolo = (int)$riadok['turnaj_kolo'];
            if ($kolo !== 0) {
                if (!isset($aktivitaProKolo[$kolo])) {
                    $aktivitaProKolo[$kolo] = [];
                }
                $aktivitaProKolo[$kolo][] = (int)$riadok['id_akce'];
            }
        }

        return $aktivitaProKolo;
    }

    // todo(tym): ber v potaz kapacitu aktivit
    /**
     * Přiřadí tým na zbylá kola turnaje, pokud v každém kole je jen jedna aktivita (bez výběru).
     *
     * @param AktivitaTym $tym Tým, který se má přiřadit
     * @return bool true pokud má tým v každém kole přiřazenou aktivitu
     */
    public function priradTymNaAutomatickaKola(AktivitaTym $tym): bool
    {
        // Zjistit ID aktivit, na které má tým už aktivitu
        $idAktivitTymu = $tym->idDalsichAktivit();

        // Zjistit všechny aktivity v každém kole turnaje
        $aktivitaProKolo = $this->idAktivitProKola();

        $vsechnyPrirazeny = true;
        // Pro každé kolo zjistit, jestli má tým už aktivitu a jestli je jen jedna aktivita
        foreach ($aktivitaProKolo as $kolo => $idAktivitVKole) {
            // Zkontrolovat, jestli má tým nějakou aktivitu v tomto kole
            if (!empty(array_intersect($idAktivitTymu, $idAktivitVKole))) {
                continue;
            }

            // Pokud je v kole jen jedna aktivita, přiřadit tým
            if (count($idAktivitVKole) === 1) {
                $tym->pridejNaAktivitu($idAktivitVKole[0]);
            } else {
                $vsechnyPrirazeny = false;
            }
        }
        return $vsechnyPrirazeny;
    }

    /**
     * @return bool true pokud má nějaké kolo více aktivit
     */
    public function jeTrebaVybratAktivityTurnaje(): bool
    {
        return !empty(array_filter(
            $this->idAktivitProKola(),
            // kola co mají na výběr více aktivit
            fn(array $idAktivitVKole) => count($idAktivitVKole) > 1,
        ));
    }
}
