<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Activity;
use App\Entity\Team;
use App\Entity\TeamMemberRegistration;
use App\Repository\ActivityRepository;
use App\Repository\TeamMemberRegistrationRepository;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Gamecon\Aktivita\InfoOTymu;

class AktivitaTymService
{
    private const HAJENI_TEAMU_HODIN = 72;
    public const CAS_NA_PRIPRAVENI_TYMU_MINUT = 30;

    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly TeamMemberRegistrationRepository $teamMemberRegistrationRepository,
        private readonly ActivityRepository $activityRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function prihlasUzivateleDoTymu(int $idUzivatele, int $idAktivity, int $idTymu, bool $ignorovatLimity = false): void
    {
        $this->zkontrolujZeNeniVJinemTymu($idUzivatele, $idAktivity);

        $novy = $idTymu === 0;
        $team = $novy
            ? $this->vytvorNovyTym($idUzivatele, $idAktivity, $ignorovatLimity)
            : $this->teamRepository->find($idTymu)
                ?? throw new \Chyba('Nepodařilo se najít tým')
            ;

        if (!$novy && !$ignorovatLimity) {
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
     * @return int[]
     */
    public function rozpracovaneTymyIds(?int $casNaPripraveniMinut = null): array
    {
        $minut = $casNaPripraveniMinut ?? self::CAS_NA_PRIPRAVENI_TYMU_MINUT;

        return array_map(
            fn (Team $team) => (int) $team->getId(),
            $this->teamRepository->findRozpracovane($minut),
        );
    }

    public function smazRozpracovaneTymy(?int $casNaPripraveniMinut = null): int
    {
        $rozpracovane = $this->teamRepository->findRozpracovane(
            $casNaPripraveniMinut ?? self::CAS_NA_PRIPRAVENI_TYMU_MINUT,
        );

        foreach ($rozpracovane as $team) {
            $this->em->remove($team);
        }

        if ($rozpracovane) {
            $this->em->flush();
        }

        return count($rozpracovane);
    }

    /**
     * @return int[]
     */
    public function expirovaneTymyIds(?int $hajeniHodin = null): array
    {
        $hodin = $hajeniHodin ?? self::HAJENI_TEAMU_HODIN;

        return array_map(
            fn (Team $team) => (int) $team->getId(),
            $this->teamRepository->findExpired($hodin),
        );
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

    public function rozebratTym(int $idTymu): void
    {
        $team = $this->teamRepository->find($idTymu);
        if (! $team) {
            return;
        }
        $this->em->remove($team);
        $this->em->flush();
    }

    public function pregenerujKodTymu(int $idTymu): int
    {
        $team = $this->teamRepository->find($idTymu);
        if (! $team) {
            throw new \Chyba('Tým nenalezen');
        }

        $idAktivity = $team->getAktivity()->first()?->getId();
        if (! $idAktivity) {
            throw new \Chyba('Tým není přiřazen k žádné aktivitě');
        }

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

    public function idTymuUzivatele(int $idUzivatele, int $idAktivity): ?int
    {
        $registration = $this->teamMemberRegistrationRepository->findByUzivatelAndAktivita($idUzivatele, $idAktivity);

        return $registration ? (int) $registration->getTeam()->getId() : null;
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

    private function najdiTeamPodleKodu(int $idAktivity, int $kodTymu): Team
    {
        $team = $this->teamRepository->findByKodNaAktivite($idAktivity, $kodTymu);
        if (! $team) {
            throw new \Chyba('Tým s kódem ' . $kodTymu . ' na této aktivitě neexistuje');
        }

        return $team;
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

    public function vytvorNovyTym(int $idUzivatele, int $idAktivity, bool $ignorovatLimity): Team
    {
        if (!$ignorovatLimity) {
            $this->zkontrolujMuzeZalozitTym($idAktivity);
        }

        $existujiciKody = array_map(
            fn (Team $t) => $t->getKod(),
            $this->teamRepository->findAllByAktivita($idAktivity),
        );
        do {
            $kod = rand(1000, 9999);
        } while (in_array($kod, $existujiciKody, true));

        $kapitan = $this->userRepository->find($idUzivatele)
            ?? throw new \Chyba('Uživatel nenalezen');
        $aktivita = $this->activityRepository->find($idAktivity)
            ?? throw new \Chyba('Aktivita nenalezena');

        $team = new Team();
        $team->setKod($kod);
        $team->setKapitan($kapitan);
        $team->setZalozen(new \DateTime());
        $team->addAktivita($aktivita);

        $this->em->persist($team);

        return $team;
    }
}
