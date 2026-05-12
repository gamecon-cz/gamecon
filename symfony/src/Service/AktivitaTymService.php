<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Activity;
use App\Entity\Team;
use Gamecon\Aktivita\StavPrihlaseni;
use App\Entity\TeamMemberRegistration;
use App\Repository\ActivityRepository;
use App\Repository\TeamMemberRegistrationRepository;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Gamecon\Aktivita\InfoOTymu;

class AktivitaTymService
{
    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly TeamMemberRegistrationRepository $teamMemberRegistrationRepository,
        private readonly ActivityRepository $activityRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function prihlasUzivateleDoTymu(int $idUzivatele, int $idAktivity, int $idTymu, int $hajeniTeamuHodin, bool $ignorovatLimity = false): void
    {
        $novy = $idTymu === 0;

        if (! $novy) {
            $existujici = $this->teamMemberRegistrationRepository->findOneBy([
                'uzivatel' => $idUzivatele,
                'team'     => $idTymu,
            ]);
            if ($existujici) {
                return;
            }

            $this->zkontrolujZeNeniVJinemTymu($idUzivatele, $idAktivity);
        }

        $team = $novy
            ? $this->vytvorNovyTym($idUzivatele, $idAktivity, $ignorovatLimity, $hajeniTeamuHodin)
            : $this->teamRepository->find($idTymu)
                ?? throw new \Chyba('Nepodařilo se najít tým')
        ;

        if (! $novy && ! $ignorovatLimity) {
            $this->zkontrolujVolnouKapacituVTymu((int) $team->getId());
        }

        $uzivatel = $this->userRepository->find($idUzivatele)
            ?? throw new \Chyba('Uživatel nenalezen');

        $registration = new TeamMemberRegistration();
        $registration->setUzivatel($uzivatel);
        $registration->setTeam($team);

        $this->em->persist($registration);
        $this->em->flush();
    }

    public function zkontrolujMuzeZalozitTym(int $idAktivity): void
    {
        if (! $this->muzePridatDalsiTym($idAktivity)) {
            throw new \Chyba('Na aktivitě je už maximální počet týmů');
        }
    }

    /**
     * @return array{int, int}|null [$team_kapacita, $pocetAktualnych] nebo null pokud team_kapacita není nastaven
     */
    public function tymAktivitaKapacity(int $idAktivity): ?array
    {
        $activity = $this->activityRepository->find($idAktivity);
        if (! $activity) {
            return null;
        }
        $limit = $activity->getTeamKapacita();
        if ($limit === null) {
            return null;
        }
        $pocet = $this->teamRepository->pocetTymuNaAktivite($idAktivity);

        return [$limit, $pocet];
    }

    public function muzePridatDalsiTym(int $idAktivity): bool
    {
        $info = $this->tymAktivitaKapacity($idAktivity);
        if ($info === null) {
            return true;
        }
        [$limit, $pocet] = $info;

        return $pocet < $limit;
    }

    public function zkontrolujVolnouKapacituVTymu(int $idTymu): void
    {
        $team = $this->teamRepository->find($idTymu);
        if (! $team) {
            return;
        }
        $pocetClenu = $this->teamMemberRegistrationRepository->pocetClenu($idTymu);
        // limit nastavený kapitánem, jinak team_max z první aktivity
        $prvniAktivita = $team->getAktivity()->first() ?: null;
        $limit = $team->getLimit() ?? $prvniAktivita?->getTeamMax();

        if ($limit !== null && $pocetClenu >= $limit) {
            throw new \Chyba('Tým je už plný');
        }
    }

    public function odhlasUzivateleOdTymu(int $idUzivatele, int $idAktivity): void
    {
        $registration = $this->teamMemberRegistrationRepository->findByUzivatelAndAktivita($idUzivatele, $idAktivity);
        if (! $registration) {
            return;
        }

        $team = $registration->getTeam();
        $idKapitan = (int) $team->getKapitan()->getId();
        $idTymu = (int) $team->getId();

        $this->em->wrapInTransaction(function () use ($registration, $team, $idUzivatele, $idKapitan, $idTymu) {
            $this->em->remove($registration);
            $this->em->flush();

            $zbyvajiciClen = $this->teamMemberRegistrationRepository->findOldestClen($idTymu);

            if (! $zbyvajiciClen) {
                $this->em->remove($team);
            } elseif ($idUzivatele === $idKapitan) {
                $team->setKapitan($zbyvajiciClen->getUzivatel());
            }

            $this->em->flush();
        });
    }

    public function infoOTymuUzivatele(int $idUzivatele, int $idAktivity): ?InfoOTymu
    {
        $row = $this->em->getConnection()->fetchAssociative(
            'SELECT
                (SELECT COUNT(*) FROM akce_tym_prihlaseni WHERE akce_tym_prihlaseni.id_tymu = akce_tym.id) AS pocet_clenu,
                COALESCE(akce_tym.`limit`, akce_seznam.team_max) AS team_limit
             FROM akce_tym
             JOIN akce_tym_prihlaseni ON akce_tym_prihlaseni.id_tymu = akce_tym.id
             JOIN akce_tym_akce ON akce_tym_akce.id_tymu = akce_tym.id AND akce_tym_akce.id_akce = ?
             JOIN akce_seznam ON akce_seznam.id_akce = akce_tym_akce.id_akce
             WHERE akce_tym_prihlaseni.id_uzivatele = ?',
            [$idAktivity, $idUzivatele],
        );
        if (! $row) {
            return null;
        }

        return new InfoOTymu(
            pocetClenu: (int) $row['pocet_clenu'],
            limit: $row['team_limit'] !== null ? (int) $row['team_limit'] : null,
        );
    }

    public function jeKapitanem(int $idUzivatele, int $idAktivity): bool
    {
        return $this->teamRepository->isKapitanNaAktivite($idUzivatele, $idAktivity);
    }

    public function maAktivitaTym(int $idAktivity): bool
    {
        return $this->teamRepository->pocetTymuNaAktivite($idAktivity) > 0;
    }

    public function nastavVerejnostTymu(int $idTymu, bool $verejny): void
    {
        $team = $this->teamRepository->find($idTymu);
        if (! $team) {
            return;
        }
        $team->setVerejny($verejny);
        $this->em->flush();
    }

    /**
     * Taky nastaví expiraci podle hajeni týmu pokud odemkne tým
     */
    public function nastavZamceniTymu(int $idTymu, bool $zamceny, int $hajeniTeamuHodin): void
    {
        $team = $this->teamRepository->find($idTymu);
        if (! $team) {
            return;
        }
        $team->setZamceny($zamceny);
        if ($zamceny) {
            $team->setVerejny(false);
        } else {
            $ted = new \DateTime();
            $expiruje = (clone $ted)->modify('+' . $hajeniTeamuHodin . ' hours');
            $team->setExpiruje($expiruje);
        }
        $this->em->flush();
    }

    public function zkontrolujZeJeKapitan(int $idTymu, int $idUzivatele): void
    {
        $team = $this->teamRepository->find($idTymu);
        if (! $team) {
            throw new \Chyba('Tým nenalezen');
        }
        if ((int) $team->getKapitan()->getId() !== $idUzivatele) {
            throw new \Chyba('Tuto akci může provést pouze kapitán týmu');
        }
    }

    public function verejnostTymu(int $idTymu): ?bool
    {
        $team = $this->teamRepository->find($idTymu);

        return $team?->isVerejny();
    }

    public function jeRozpracovany(int $idTymu): bool
    {
        $team = $this->teamRepository->find($idTymu);
        if (! $team) {
            return false;
        }

        return $team->getClenove()->count() === 0;
    }

    /**
     * Zkontroluje, zda má tým přiřazené alespoň jednu aktivitu z každého kola turnaje.
     * Pokud tým není na turnajové aktivitě, vrací true.
     * Tým je vždy na aktivitách maximálně jednoho turnaje.
     */
    public function maPrirazeneVsechnaKolaTurnaje(int $idTymu): bool
    {
        $team = $this->teamRepository->find($idTymu);
        if (! $team) {
            throw new \Chyba('Tým nenalezen');
        }

        $aktivity = $team->getAktivity();
        if ($aktivity->isEmpty()) {
            throw new \Chyba('Tým nemá aktivitu');
        }

        // Zjistíme turnaj z první aktivity (tým je maximálně na jednom turnaji)
        $turnaj = null;
        foreach ($aktivity as $aktivita) {
            if ($aktivita->getTournament()) {
                $turnaj = $aktivita->getTournament();
                break;
            }
        }

        // Pokud tým není na žádném turnaji, vrátíme true
        if (! $turnaj) {
            return true;
        }

        // Zjistíme maximální číslo kola v turnaji
        $maxKolo = (int) max(array_map(
            fn ($a) => $a->getTurnajKolo() ?? 0,
            $turnaj->getAktivity()->toArray(),
        ));

        // Zjistíme čísla kol, ve kterých má tým aktivitu
        $teamKola = array_map(
            fn ($a) => $a->getTurnajKolo(),
            array_filter($aktivity->toArray(), fn ($a) => $a->getTurnajKolo() !== null),
        );

        // Zkontrolujeme, že tým má aktivitu v každém kole
        for ($kolo = 1; $kolo <= $maxKolo; ++$kolo) {
            if (! in_array($kolo, $teamKola, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return int[]
     */
    public function rozpracovaneTymyIds(int $casNaPripraveniMinut): array
    {
        return array_map(
            fn (Team $team) => (int) $team->getId(),
            $this->teamRepository->findRozpracovane($casNaPripraveniMinut),
        );
    }

    public function smazRozpracovaneTymy(int $casNaPripraveniMinut): int
    {
        $rozpracovane = $this->teamRepository->findRozpracovane(
            $casNaPripraveniMinut,
        );

        if (empty($rozpracovane)) {
            return 0;
        }

        foreach ($rozpracovane as $team) {
            $this->em->remove($team);
        }

        $this->em->flush();

        return count($rozpracovane);
    }

    /**
     * @return Team[]
     */
    public function expirovaneTymy(): array
    {
        return $this->teamRepository->findExpired();
    }

    /**
     * @return Team[]
     */
    public function tymyBezAktivity(int $rok): array
    {
        return $this->teamRepository->findBezAktivity($rok);
    }

    /**
     * Týmy na turnajové aktivitě kde v některém kole nemají právě jednu aktivitu (0 nebo 2+).
     * @return array{id: int, nazev: string, aktivita: string, kola: array}[]
     */
    public function tymySPatnymKolemTurnaje(int $rok): array
    {
        $conn = $this->em->getConnection();

        $badTeams = $conn->fetchAllAssociative(
            'SELECT DISTINCT akce_tym.id AS id_tymu,
                    akce_tym.nazev AS nazev_tymu,
                    turnaje.nazev AS nazev_turnaje,
                    turnaje.id_turnaje
             FROM akce_tym
             JOIN akce_tym_akce ON akce_tym_akce.id_tymu = akce_tym.id
             JOIN akce_seznam ON akce_seznam.id_akce = akce_tym_akce.id_akce
             JOIN turnaje ON turnaje.id_turnaje = akce_seznam.id_turnaje
             WHERE akce_seznam.id_turnaje IS NOT NULL
               AND akce_seznam.rok = ?
               AND akce_tym.id IN (
                   SELECT tymy_inner.id
                   FROM akce_tym AS tymy_inner
                   JOIN akce_tym_akce AS vazby_inner ON vazby_inner.id_tymu = tymy_inner.id
                   JOIN akce_seznam AS aktivity_inner ON aktivity_inner.id_akce = vazby_inner.id_akce
                   WHERE aktivity_inner.id_turnaje IS NOT NULL
                     AND aktivity_inner.rok = ?
                   GROUP BY tymy_inner.id, aktivity_inner.id_turnaje, aktivity_inner.turnaj_kolo
                   HAVING COUNT(*) != 1
               )',
            [$rok, $rok],
        );

        $result = [];
        foreach ($badTeams as $team) {
            $aktivityTurnaje = $conn->fetchAllAssociative(
                'SELECT akce_seznam.id_akce,
                        akce_seznam.nazev_akce,
                        akce_seznam.turnaj_kolo,
                        akce_seznam.zacatek,
                        CASE WHEN akce_tym_akce.id_akce IS NOT NULL THEN 1 ELSE 0 END AS prihlasena
                 FROM akce_seznam
                 LEFT JOIN akce_tym_akce ON akce_tym_akce.id_akce = akce_seznam.id_akce
                     AND akce_tym_akce.id_tymu = ?
                 WHERE akce_seznam.id_turnaje = ?
                   AND akce_seznam.rok = ?
                 ORDER BY akce_seznam.turnaj_kolo, akce_seznam.zacatek',
                [(int) $team['id_tymu'], (int) $team['id_turnaje'], $rok],
            );

            $kola = [];
            foreach ($aktivityTurnaje as $aktivita) {
                $kolo = (int) $aktivita['turnaj_kolo'];
                if (! isset($kola[$kolo])) {
                    $kola[$kolo] = [
                        'cislo'    => $kolo,
                        'cas'      => $aktivita['zacatek']
                            ? (new \DateTime($aktivita['zacatek']))->format('j.n. H:i')
                            : '',
                        'aktivity' => [],
                    ];
                }
                $kola[$kolo]['aktivity'][] = [
                    'id'        => (int) $aktivita['id_akce'],
                    'nazev'     => $aktivita['nazev_akce'],
                    'prihlasena' => (bool) $aktivita['prihlasena'],
                ];
            }

            $result[] = [
                'id'       => (int) $team['id_tymu'],
                'nazev'    => $team['nazev_tymu'] ?? '',
                'aktivita' => $team['nazev_turnaje'],
                'kola'     => array_values($kola),
            ];
        }

        return $result;
    }

    /**
     * Hráči přihlášení v týmu kteří nejsou přihlášeni na všechny aktivity jejich týmu.
     * @return array{nick: string, jmeno: string, idTymu: int, nazevTymu: string, aktivita: string, chybiPrihlaska: string}[]
     */
    public function hraciNeprihlaseniNaAktivityTymu(int $rok): array
    {
        $rows = $this->em->getConnection()->fetchAllAssociative(
            'SELECT uzivatele_hodnoty.login_uzivatele AS nick,
                    CONCAT(uzivatele_hodnoty.jmeno_uzivatele, \' \', uzivatele_hodnoty.prijmeni_uzivatele) AS jmeno,
                    akce_tym.id AS id_tymu,
                    akce_tym.nazev AS nazev_tymu,
                    akce_seznam.nazev_akce,
                    akce_seznam.zacatek,
                    akce_seznam.turnaj_kolo
             FROM akce_tym_prihlaseni
             JOIN uzivatele_hodnoty ON uzivatele_hodnoty.id_uzivatele = akce_tym_prihlaseni.id_uzivatele
             JOIN akce_tym ON akce_tym.id = akce_tym_prihlaseni.id_tymu
             JOIN akce_tym_akce ON akce_tym_akce.id_tymu = akce_tym.id
             JOIN akce_seznam ON akce_seznam.id_akce = akce_tym_akce.id_akce
             LEFT JOIN akce_prihlaseni ON akce_prihlaseni.id_akce = akce_seznam.id_akce
                 AND akce_prihlaseni.id_uzivatele = akce_tym_prihlaseni.id_uzivatele
                 AND akce_prihlaseni.id_stavu_prihlaseni != ?
             WHERE akce_prihlaseni.id IS NULL
               AND akce_seznam.rok = ?
             ORDER BY uzivatele_hodnoty.login_uzivatele, akce_seznam.turnaj_kolo',
            [StavPrihlaseni::SLEDUJICI, $rok],
        );

        return array_map(static function (array $row): array {
            $casStr = $row['zacatek']
                ? (new \DateTime($row['zacatek']))->format('j.n. H:i')
                : '';
            $chybi = $row['turnaj_kolo']
                ? 'Kolo ' . $row['turnaj_kolo'] . ' – ' . $row['nazev_akce'] . ' (' . $casStr . ')'
                : $row['nazev_akce'] . ' (' . $casStr . ')';

            return [
                'nick'           => $row['nick'],
                'jmeno'          => $row['jmeno'],
                'idTymu'         => (int) $row['id_tymu'],
                'nazevTymu'      => $row['nazev_tymu'] ?? '',
                'aktivita'       => $row['nazev_akce'],
                'chybiPrihlaska' => $chybi,
            ];
        }, $rows);
    }

    /**
     * Hráči přihlášení na týmovou aktivitu ale nejsou v žádném týmu nebo jsou ve více týmech.
     * @return array{nick: string, jmeno: string, aktivita: string, chyba: string, idUzivatele: int, idAktivity: int}[]
     */
    public function hraciSPatnymTymem(int $rok): array
    {
        $conn = $this->em->getConnection();

        $bezTymu = $conn->fetchAllAssociative(
            'SELECT uzivatele_hodnoty.login_uzivatele AS nick,
                    CONCAT(uzivatele_hodnoty.jmeno_uzivatele, \' \', uzivatele_hodnoty.prijmeni_uzivatele) AS jmeno,
                    akce_seznam.nazev_akce,
                    akce_prihlaseni.id_uzivatele,
                    akce_prihlaseni.id_akce
             FROM akce_prihlaseni
             JOIN uzivatele_hodnoty ON uzivatele_hodnoty.id_uzivatele = akce_prihlaseni.id_uzivatele
             JOIN akce_seznam ON akce_seznam.id_akce = akce_prihlaseni.id_akce
             WHERE akce_seznam.teamova = 1
               AND akce_seznam.rok = ?
               AND akce_prihlaseni.id_stavu_prihlaseni != ?
               AND NOT EXISTS (
                   SELECT 1
                   FROM akce_tym_prihlaseni
                   JOIN akce_tym_akce ON akce_tym_akce.id_tymu = akce_tym_prihlaseni.id_tymu
                   WHERE akce_tym_prihlaseni.id_uzivatele = akce_prihlaseni.id_uzivatele
                     AND akce_tym_akce.id_akce = akce_prihlaseni.id_akce
               )
             ORDER BY uzivatele_hodnoty.login_uzivatele',
            [$rok, StavPrihlaseni::SLEDUJICI],
        );

        $viceTymu = $conn->fetchAllAssociative(
            'SELECT uzivatele_hodnoty.login_uzivatele AS nick,
                    CONCAT(uzivatele_hodnoty.jmeno_uzivatele, \' \', uzivatele_hodnoty.prijmeni_uzivatele) AS jmeno,
                    akce_seznam.nazev_akce,
                    akce_tym_prihlaseni.id_uzivatele,
                    akce_seznam.id_akce,
                    GROUP_CONCAT(COALESCE(akce_tym.nazev, CONCAT(\'#\', akce_tym.id))
                        ORDER BY akce_tym.id SEPARATOR \', \') AS nazvy_tymu
             FROM akce_tym_prihlaseni
             JOIN uzivatele_hodnoty ON uzivatele_hodnoty.id_uzivatele = akce_tym_prihlaseni.id_uzivatele
             JOIN akce_tym ON akce_tym.id = akce_tym_prihlaseni.id_tymu
             JOIN akce_tym_akce ON akce_tym_akce.id_tymu = akce_tym.id
             JOIN akce_seznam ON akce_seznam.id_akce = akce_tym_akce.id_akce
             WHERE akce_seznam.rok = ?
             GROUP BY akce_tym_prihlaseni.id_uzivatele, akce_seznam.id_akce,
                      uzivatele_hodnoty.login_uzivatele, uzivatele_hodnoty.jmeno_uzivatele,
                      uzivatele_hodnoty.prijmeni_uzivatele, akce_seznam.nazev_akce
             HAVING COUNT(DISTINCT akce_tym_prihlaseni.id_tymu) > 1
             ORDER BY uzivatele_hodnoty.login_uzivatele',
            [$rok],
        );

        $result = [];
        foreach ($bezTymu as $row) {
            $result[] = [
                'nick'        => $row['nick'],
                'jmeno'       => $row['jmeno'],
                'aktivita'    => $row['nazev_akce'],
                'chyba'       => 'není v žádném týmu',
                'idUzivatele' => (int) $row['id_uzivatele'],
                'idAktivity'  => (int) $row['id_akce'],
            ];
        }
        foreach ($viceTymu as $row) {
            $result[] = [
                'nick'        => $row['nick'],
                'jmeno'       => $row['jmeno'],
                'aktivita'    => $row['nazev_akce'],
                'chyba'       => 've více týmech: ' . $row['nazvy_tymu'],
                'idUzivatele' => (int) $row['id_uzivatele'],
                'idAktivity'  => (int) $row['id_akce'],
            ];
        }

        return $result;
    }

    /**
     * Vrátí připravené týmy (mají alespoň jednoho člena) kde kapitán není přihlášen jako člen.
     *
     * @return Team[]
     */
    public function pripraveneTymyBezKapitana(int $rok): array
    {
        return $this->teamRepository->findPripraveneBezKapitana($rok);
    }

    /**
     * @return \Uzivatel[] seřazení: kapitán první, pak ostatní podle pořadí přihlášení
     */
    public function clenoveTymu(int $idTymu): array
    {
        $team = $this->teamRepository->find($idTymu);
        if (! $team) {
            return [];
        }

        $idKapitana = (int) $team->getKapitan()->getId();
        $ids = array_column(
            $this->em->getConnection()->fetchAllAssociative(
                'SELECT akce_tym_prihlaseni.id_uzivatele
                 FROM akce_tym_prihlaseni
                 WHERE akce_tym_prihlaseni.id_tymu = ?
                 ORDER BY (akce_tym_prihlaseni.id_uzivatele = ?) DESC, akce_tym_prihlaseni.id ASC',
                [$idTymu, $idKapitana],
            ),
            'id_uzivatele',
        );

        return \Uzivatel::zIds(array_map('intval', $ids));
    }

    /**
     * @return Team[]
     */
    public function vsechnyTymyAktivity(int $idAktivity): array
    {
        return $this->teamRepository->findAllByAktivita($idAktivity);
    }

    public function smazat(int $idTymu): void
    {
        $team = $this->teamRepository->find($idTymu);
        if (! $team) {
            return;
        }

        $this->em->wrapInTransaction(function () use ($team) {
            $idClenu = $team->getClenove()
                ->map(fn(TeamMemberRegistration $c) => $c->getUzivatel()->getId())
                ->filter(fn(?int $id) => $id !== null)
                ->toArray();
            $idAktivit = $team->getAktivity()
                ->map(fn(Activity $a) => $a->getId())
                ->filter(fn(?int $id) => $id !== null)
                ->toArray();

            if ($idClenu && $idAktivit) {
                $this->em->getConnection()->executeStatement(
                    'DELETE FROM akce_prihlaseni WHERE id_uzivatele IN (?) AND id_akce IN (?)',
                    [$idClenu, $idAktivit],
                    [\Doctrine\DBAL\ArrayParameterType::INTEGER, \Doctrine\DBAL\ArrayParameterType::INTEGER],
                );
            }

            $this->em->remove($team);
            $this->em->flush();
        });
    }

    public function pregenerujKodTymu(int $idTymu): int
    {
        $team = $this->teamRepository->find($idTymu);
        if (! $team) {
            throw new \Chyba('Tým nenalezen');
        }

        $prvniAktivita = $team->getAktivity()->first();
        if (! $prvniAktivita) {
            throw new \Chyba('Tým není přiřazen k žádné aktivitě');
        }
        $idAktivity = $prvniAktivita->getId();

        $existujiciKody = array_map(
            fn (Team $t) => $t->getKod(),
            $this->teamRepository->findAllByAktivita($idAktivity),
        );
        do {
            $novyKod = rand(1000, 9999);
        } while (in_array($novyKod, $existujiciKody, true));

        $team->setKod($novyKod);
        $this->em->flush();

        return $novyKod;
    }

    public function pridejTymNaAktivitu(int $idTymu, int $idAktivity): void
    {
        $team = $this->teamRepository->find($idTymu);
        $activity = $this->activityRepository->find($idAktivity);
        if (! $team || ! $activity) {
            return;
        }
        $team->addAktivita($activity);
        $this->em->flush();
    }

    public function tymKapitanaNaAktivite(int $idKapitana, int $idAktivity): ?Team
    {
        return $this->teamRepository->findByKapitanNaAktivite($idKapitana, $idAktivity);
    }

    public function tymUzivateleNaAktivite(int $idUzivatele, int $idAktivity): ?Team
    {
        $registration = $this->teamMemberRegistrationRepository->findByUzivatelAndAktivita($idUzivatele, $idAktivity);

        return $registration ? $registration->getTeam() : null;
    }

    public function casZalozeniTymuUzivatele(int $idUzivatele, int $idAktivity): ?int
    {
        $registration = $this->teamMemberRegistrationRepository->findByUzivatelAndAktivita($idUzivatele, $idAktivity);

        return $registration?->getTeam()->getZalozen()?->getTimestamp();
    }

    /**
     * @return int[]
     */
    public function idDalsichAktivitTymu(int $idTymu, int $vyjmaIdAktivity = -1): array
    {
        $team = $this->teamRepository->find($idTymu);
        if (! $team) {
            return [];
        }
        $aktivity = $team->getAktivity();
        if ($vyjmaIdAktivity >= 0) {
            $aktivity = $aktivity->filter(fn (Activity $a) => $a->getId() !== $vyjmaIdAktivity);
        }

        return array_map(fn (Activity $a) => (int) $a->getId(), $aktivity->toArray());
    }

    public function idKapitanaTymu(int $idTymu): ?int
    {
        $team = $this->teamRepository->find($idTymu);

        return $team ? (int) $team->getKapitan()->getId() : null;
    }

    private function zkontrolujZeNeniVJinemTymu(int $idUzivatele, int $idAktivity): void
    {
        $existing = $this->teamMemberRegistrationRepository->findByUzivatelAndAktivita($idUzivatele, $idAktivity);
        if ($existing) {
            throw new \Chyba('Už jsi přihlášen v týmu na této aktivitě');
        }
    }

    public function casZalozeniMs(int $idTymu): ?int
    {
        $team = $this->teamRepository->find($idTymu);

        return $team?->getZalozen() !== null ? $team->getZalozen()->getTimestamp() * 1000 : null;
    }

    public function casExpiraceMs(int $idTymu): ?int
    {
        $team = $this->teamRepository->find($idTymu);

        return $team?->getExpiruje() !== null ? $team->getExpiruje()->getTimestamp() * 1000 : null;
    }

    public function limitTymu(int $idTymu): ?int
    {
        $team = $this->teamRepository->find($idTymu);
        if (! $team) {
            return null;
        }

        $prvniAktivita = $team->getAktivity()->first() ?: null;

        return $team->getLimit() ?? $prvniAktivita?->getTeamMax();
    }

    public function nastavLimitTymu(int $idTymu, int $limit): void
    {
        $team = $this->teamRepository->find($idTymu)
            ?? throw new \Chyba('Tým nenalezen');
        $pocetClenu = $this->teamMemberRegistrationRepository->pocetClenu($idTymu);
        if ($limit < $pocetClenu) {
            throw new \Chyba('Limit nemůže být nižší než aktuální počet členů');
        }
        $prvniAktivita = $team->getAktivity()->first() ?: null;
        $minKapacita = $prvniAktivita?->getTeamMin();
        if ($minKapacita !== null && $limit < $minKapacita) {
            throw new \Chyba('Limit nemůže být nižší než minimální kapacita (' . $minKapacita . ')');
        }
        $team->setLimit($limit);
        $this->em->flush();
    }

    public function nastavKapitana(int $idTymu, int $idNovehoKapitana): void
    {
        $team = $this->teamRepository->find($idTymu)
            ?? throw new \Chyba('Tým nenalezen');
        $novyKapitan = $this->userRepository->find($idNovehoKapitana)
            ?? throw new \Chyba('Uživatel nenalezen');
        $registrace = $this->teamMemberRegistrationRepository->findByUzivatelAndTeam($idNovehoKapitana, $idTymu);
        if (! $registrace) {
            throw new \Chyba('Uživatel není členem tohoto týmu');
        }
        $team->setKapitan($novyKapitan);
        $this->em->flush();
    }

    public function vytvorNovyTym(int $idUzivatele, int $idAktivity, bool $ignorovatLimity, int $hajeniTeamuHodin): Team
    {
        $kapitan = $this->userRepository->find($idUzivatele)
            ?? throw new \Chyba('Uživatel nenalezen');
        $aktivita = $this->activityRepository->find($idAktivity)
            ?? throw new \Chyba('Aktivita nenalezena');

        $this->zkontrolujZeNeniVJinemTymu($idUzivatele, $idAktivity);

        if (! $ignorovatLimity) {
            $this->zkontrolujMuzeZalozitTym($idAktivity);
        }

        $existujiciKody = array_map(
            fn (Team $t) => $t->getKod(),
            $this->teamRepository->findAllByAktivita($idAktivity),
        );
        do {
            $kod = rand(1000, 9999);
        } while (in_array($kod, $existujiciKody, true));

        // Josh Radnor
        $ted = new \DateTime();
        $expiruje = (clone $ted)->modify('+' . $hajeniTeamuHodin . ' hours');

        $team = new Team();
        $team->setKod($kod);
        $team->setKapitan($kapitan);
        $team->setZalozen($ted);
        $team->setExpiruje($expiruje);
        $team->addAktivita($aktivita);

        $this->em->persist($team);
        $this->em->flush();

        return $team;
    }
}
