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
use Gamecon\Aktivita\TymVSeznamu;
use Gamecon\Aktivita\VerejnyTym;

class AktivitaTymService
{
    private const HAJENI_TEAMU_HODIN = 72;

    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly TeamMemberRegistrationRepository $teamMemberRegistrationRepository,
        private readonly ActivityRepository $activityRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function prihlasUzivateleDoTymu(int $idUzivatele, int $idAktivity, int $kodTymu, bool $ignorovatLimity = false): void
    {
        $this->zkontrolujZeNeniVJinemTymu($idUzivatele, $idAktivity);

        if ($kodTymu === 0) {
            $team = $this->vytvorNovyTym($idUzivatele, $idAktivity, $ignorovatLimity);
        } else {
            $idTymu = $this->najdiTymPodleKodu($idAktivity, $kodTymu);
            $team   = $this->teamRepository->find($idTymu)
                ?? throw new \Chyba('Tým nenalezen');
            if (!$ignorovatLimity) {
                $this->zkontrolujVolnouKapacituVTymu($idTymu);
            }
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
        if (!$this->muzePridatDalsiTym($idAktivity)) {
            throw new \Chyba('Na aktivitě je už maximální počet týmů');
        }
    }

    /** @return [int|null, int] [$team_kapacita, $pocetAktualnych] nebo null pokud team_kapacita není nastaven */
    public function tymAktivitaKapacity(int $idAktivity): ?array
    {
        $activity = $this->activityRepository->find($idAktivity);
        if (!$activity) {
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

    public function najdiTymPodleKodu(int $idAktivity, int $kodTymu): int
    {
        $team = $this->teamRepository->findByKodNaAktivite($idAktivity, $kodTymu);
        if (!$team) {
            throw new \Chyba('Tým s kódem ' . $kodTymu . ' na této aktivitě neexistuje');
        }

        return (int)$team->getId();
    }

    public function zkontrolujVolnouKapacituVTymu(int $idTymu): void
    {
        $team = $this->teamRepository->find($idTymu);
        if (!$team) {
            return;
        }
        $pocetClenu = $this->teamMemberRegistrationRepository->pocetClenu($idTymu);
        // limit nastavený kapitánem, jinak team_max z první aktivity
        $limit = $team->getLimit() ?? $team->getAktivity()->first()?->getTeamMax();

        if ($limit !== null && $pocetClenu >= $limit) {
            throw new \Chyba('Tým je už plný');
        }
    }

    public function odhlasUzivateleOdTymu(int $idUzivatele, int $idAktivity): void
    {
        $registration = $this->teamMemberRegistrationRepository->findByUzivatelAndAktivita($idUzivatele, $idAktivity);
        if (!$registration) {
            return;
        }

        $team      = $registration->getTeam();
        $idKapitan = (int)$team->getKapitan()->getId();
        $idTymu    = (int)$team->getId();

        $this->em->wrapInTransaction(function () use ($registration, $team, $idUzivatele, $idKapitan, $idTymu) {
            $this->em->remove($registration);
            $this->em->flush();

            $zbyvajiciClen = $this->teamMemberRegistrationRepository->findOldestClen($idTymu);

            if (!$zbyvajiciClen) {
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
        if (!$row) {
            return null;
        }

        return new InfoOTymu(
            pocetClenu: (int)$row['pocet_clenu'],
            limit: $row['team_limit'] !== null ? (int)$row['team_limit'] : null,
        );
    }

    public function vratKodTymuProUzivatele(int $idUzivatele, int $idAktivity): int
    {
        $registration = $this->teamMemberRegistrationRepository->findByUzivatelAndAktivita($idUzivatele, $idAktivity);

        return $registration ? $registration->getTeam()->getKod() : 0;
    }

    public function jeKapitanem(int $idUzivatele, int $idAktivity): bool
    {
        return $this->teamRepository->isKapitanNaAktivite($idUzivatele, $idAktivity);
    }

    public function maAktivitaTym(int $idAktivity): bool
    {
        return $this->teamRepository->pocetTymuNaAktivite($idAktivity) > 0;
    }

    /** @return VerejnyTym[] */
    public function verejneTymy(int $idAktivity): array
    {
        $rows = $this->em->getConnection()->fetchAllAssociative(
            'SELECT akce_tym.kod, akce_tym.nazev,
                    COALESCE(akce_tym.`limit`, akce_seznam.team_max) AS team_limit,
                    (SELECT COUNT(*) FROM akce_tym_prihlaseni WHERE akce_tym_prihlaseni.id_tymu = akce_tym.id) AS pocet_clenu
             FROM akce_tym
             JOIN akce_tym_akce ON akce_tym_akce.id_tymu = akce_tym.id AND akce_tym_akce.id_akce = ?
             JOIN akce_seznam ON akce_seznam.id_akce = akce_tym_akce.id_akce
             WHERE akce_tym.verejny = 1',
            [$idAktivity],
        );

        return array_map(
            fn(array $row) => new VerejnyTym(
                kod: (int)$row['kod'],
                nazev: $row['nazev'],
                pocetClenu: (int)$row['pocet_clenu'],
                limit: $row['team_limit'] !== null ? (int)$row['team_limit'] : null,
            ),
            $rows,
        );
    }

    public function nastavVerejnostTymu(int $kodTymu, int $idAktivity, bool $verejny): void
    {
        $team = $this->teamRepository->findByKodNaAktivite($idAktivity, $kodTymu);
        if (!$team) {
            return;
        }
        $team->setVerejny($verejny);
        $this->em->flush();
    }

    public function zkontrolujZeJeKapitan(int $kodTymu, int $idAktivity, int $idUzivatele): void
    {
        $team = $this->teamRepository->findByKodNaAktivite($idAktivity, $kodTymu);
        if (!$team) {
            throw new \Chyba('Tým s kódem ' . $kodTymu . ' na této aktivitě neexistuje');
        }
        if ((int)$team->getKapitan()->getId() !== $idUzivatele) {
            throw new \Chyba('Tuto akci může provést pouze kapitán týmu');
        }
    }

    public function verejnostTymuPodleKodu(int $kodTymu, int $idAktivity): ?bool
    {
        $team = $this->teamRepository->findByKodNaAktivite($idAktivity, $kodTymu);

        return $team?->isVerejny();
    }

    public function pocetVolnychMistVVerejnychTymech(int $idAktivity): int
    {
        $tymy      = $this->verejneTymy($idAktivity);
        $volnaMista = 0;
        foreach ($tymy as $tym) {
            if ($tym->limit !== null) {
                $volnaMista += max(0, $tym->limit - $tym->pocetClenu);
            }
        }

        return $volnaMista;
    }

    /** @return int[] */
    public function expirovaneTymyIds(?int $hajeniHodin = null): array
    {
        $hodin = $hajeniHodin ?? self::HAJENI_TEAMU_HODIN;

        return array_map(
            fn(Team $team) => (int)$team->getId(),
            $this->teamRepository->findExpired($hodin),
        );
    }

    /** @return \Uzivatel[] seřazení: kapitán první, pak ostatní podle pořadí přihlášení */
    public function clenoveTymu(int $kodTymu, int $idAktivity): array
    {
        $idKapitana = $this->idKapitanaTymu($kodTymu, $idAktivity);
        $ids        = array_column(
            $this->em->getConnection()->fetchAllAssociative(
                'SELECT akce_tym_prihlaseni.id_uzivatele
                 FROM akce_tym_prihlaseni
                 JOIN akce_tym ON akce_tym.id = akce_tym_prihlaseni.id_tymu
                 JOIN akce_tym_akce ON akce_tym_akce.id_tymu = akce_tym.id AND akce_tym_akce.id_akce = ?
                 WHERE akce_tym.kod = ?
                 ORDER BY (akce_tym_prihlaseni.id_uzivatele = ?) DESC, akce_tym_prihlaseni.id ASC',
                [$idAktivity, $kodTymu, $idKapitana],
            ),
            'id_uzivatele',
        );

        return \Uzivatel::zIds(array_map('intval', $ids));
    }

    /** @return TymVSeznamu[] */
    public function vsechnyTymy(int $idAktivity): array
    {
        $rows = $this->em->getConnection()->fetchAllAssociative(
            'SELECT akce_tym.kod, akce_tym.nazev, akce_tym.verejny,
                    COALESCE(akce_tym.`limit`, akce_seznam.team_max) AS team_limit,
                    (SELECT COUNT(*) FROM akce_tym_prihlaseni WHERE akce_tym_prihlaseni.id_tymu = akce_tym.id) AS pocet_clenu
             FROM akce_tym
             JOIN akce_tym_akce ON akce_tym_akce.id_tymu = akce_tym.id AND akce_tym_akce.id_akce = ?
             JOIN akce_seznam ON akce_seznam.id_akce = akce_tym_akce.id_akce',
            [$idAktivity],
        );

        return array_map(
            fn(array $row) => new TymVSeznamu(
                kod: (int)$row['kod'],
                nazev: $row['nazev'],
                pocetClenu: (int)$row['pocet_clenu'],
                limit: $row['team_limit'] !== null ? (int)$row['team_limit'] : null,
                verejny: (bool)(int)$row['verejny'],
            ),
            $rows,
        );
    }

    public function pregenerujKodTymu(int $kodTymu, int $idAktivity): int
    {
        $team = $this->teamRepository->findByKodNaAktivite($idAktivity, $kodTymu);
        if (!$team) {
            throw new \Chyba('Tým s kódem ' . $kodTymu . ' na této aktivitě neexistuje');
        }

        $existujiciKody = array_map(
            fn(Team $t) => $t->getKod(),
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
        $team     = $this->teamRepository->find($idTymu);
        $activity = $this->activityRepository->find($idAktivity);
        if (!$team || !$activity) {
            return;
        }
        $team->addAktivita($activity);
        $this->em->flush();
    }

    public function idTymuUzivatele(int $idUzivatele, int $idAktivity): ?int
    {
        $registration = $this->teamMemberRegistrationRepository->findByUzivatelAndAktivita($idUzivatele, $idAktivity);

        return $registration ? (int)$registration->getTeam()->getId() : null;
    }

    public function casZalozeniTymuUzivatele(int $idUzivatele, int $idAktivity): ?int
    {
        $registration = $this->teamMemberRegistrationRepository->findByUzivatelAndAktivita($idUzivatele, $idAktivity);

        return $registration?->getTeam()->getZalozen()?->getTimestamp();
    }

    /** @return int[] */
    public function idDalsichAktivitTymu(int $idTymu, int $vyjmaIdAktivity = -1): array
    {
        $team = $this->teamRepository->find($idTymu);
        if (!$team) {
            return [];
        }
        $aktivity = $team->getAktivity();
        if ($vyjmaIdAktivity >= 0) {
            $aktivity = $aktivity->filter(fn(Activity $a) => $a->getId() !== $vyjmaIdAktivity);
        }

        return array_map(fn(Activity $a) => (int)$a->getId(), $aktivity->toArray());
    }

    public function idKapitanaTymu(int $kodTymu, int $idAktivity): ?int
    {
        $team = $this->teamRepository->findByKodNaAktivite($idAktivity, $kodTymu);

        return $team ? (int)$team->getKapitan()->getId() : null;
    }

    private function zkontrolujZeNeniVJinemTymu(int $idUzivatele, int $idAktivity): void
    {
        $existing = $this->teamMemberRegistrationRepository->findByUzivatelAndAktivita($idUzivatele, $idAktivity);
        if ($existing) {
            throw new \Chyba('Už jsi přihlášen v týmu na této aktivitě');
        }
    }

    private function vytvorNovyTym(int $idUzivatele, int $idAktivity, bool $ignorovatLimity): Team
    {
        if (!$ignorovatLimity) {
            $this->zkontrolujMuzeZalozitTym($idAktivity);
        }

        $existujiciKody = array_map(
            fn(Team $t) => $t->getKod(),
            $this->teamRepository->findAllByAktivita($idAktivity),
        );
        do {
            $kod = rand(1000, 9999);
        } while (in_array($kod, $existujiciKody, true));

        $kapitan  = $this->userRepository->find($idUzivatele)
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
