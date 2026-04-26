<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TeamMemberRegistrationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamMemberRegistrationRepository::class)]
#[ORM\Table(name: 'akce_tym_prihlaseni')]
#[ORM\UniqueConstraint(name: 'UNIQ_id_uzivatele_id_tymu', columns: ['id_uzivatele', 'id_tymu'])]
class TeamMemberRegistration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_uzivatele', referencedColumnName: 'id_uzivatele', nullable: false)]
    private User $uzivatel;

    #[ORM\ManyToOne(targetEntity: Team::class, inversedBy: 'clenove')]
    #[ORM\JoinColumn(name: 'id_tymu', referencedColumnName: 'id', nullable: false)]
    private Team $team;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUzivatel(): User
    {
        return $this->uzivatel;
    }

    public function setUzivatel(User $uzivatel): self
    {
        $this->uzivatel = $uzivatel;

        return $this;
    }

    public function getTeam(): Team
    {
        return $this->team;
    }

    public function setTeam(Team $team): self
    {
        $this->team = $team;

        return $this;
    }
}
