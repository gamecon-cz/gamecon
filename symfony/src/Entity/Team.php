<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TeamRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Legacy @see \Gamecon\Aktivita\AktivitaTym
 */
#[ORM\Entity(repositoryClass: TeamRepository::class)]
#[ORM\Table(name: 'akce_tym')]
class Team
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\Column(name: 'kod', type: Types::INTEGER, nullable: false)]
    private int $kod;

    #[ORM\Column(name: 'nazev', type: Types::STRING, length: 255, nullable: true)]
    private ?string $nazev = null;

    #[ORM\Column(name: '`limit`', type: Types::INTEGER, nullable: true)]
    private ?int $limit = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_kapitan', referencedColumnName: 'id_uzivatele', nullable: false)]
    private User $kapitan;

    #[ORM\Column(name: 'zalozen', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $zalozen = null;

    #[ORM\Column(name: 'verejny', type: Types::BOOLEAN, nullable: false, options: [
        'default' => 0,
    ])]
    private bool $verejny = false;

    #[ORM\Column(name: 'zamceny', type: Types::BOOLEAN, nullable: false, options: [
        'default' => 0,
        'comment' => 'zamčený tým nelze editovat',
    ])]
    private bool $zamceny = false;

    /**
     * @var Collection<int, Activity>
     */
    #[ORM\ManyToMany(targetEntity: Activity::class)]
    #[ORM\JoinTable(name: 'akce_tym_akce')]
    #[ORM\JoinColumn(name: 'id_tymu', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'id_akce', referencedColumnName: 'id_akce')]
    private Collection $aktivity;

    /**
     * @var Collection<int, TeamMemberRegistration>
     */
    #[ORM\OneToMany(targetEntity: TeamMemberRegistration::class, mappedBy: 'team', cascade: ['remove'])]
    private Collection $clenove;

    public function __construct()
    {
        $this->aktivity = new ArrayCollection();
        $this->clenove = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKod(): int
    {
        return $this->kod;
    }

    public function setKod(int $kod): self
    {
        $this->kod = $kod;

        return $this;
    }

    public function getNazev(): ?string
    {
        return $this->nazev;
    }

    public function setNazev(?string $nazev): self
    {
        $this->nazev = $nazev;

        return $this;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function setLimit(?int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    public function getKapitan(): User
    {
        return $this->kapitan;
    }

    public function setKapitan(User $kapitan): self
    {
        $this->kapitan = $kapitan;

        return $this;
    }

    public function getZalozen(): ?\DateTimeInterface
    {
        return $this->zalozen;
    }

    public function setZalozen(?\DateTimeInterface $zalozen): self
    {
        $this->zalozen = $zalozen;

        return $this;
    }

    public function isVerejny(): bool
    {
        return $this->verejny;
    }

    public function setVerejny(bool $verejny): self
    {
        $this->verejny = $verejny;

        return $this;
    }

    public function isZamceny(): bool
    {
        return $this->zamceny;
    }

    public function setZamceny(bool $zamceny): self
    {
        $this->zamceny = $zamceny;

        return $this;
    }

    /**
     * @return Collection<int, Activity>
     */
    public function getAktivity(): Collection
    {
        return $this->aktivity;
    }

    public function addAktivita(Activity $aktivita): self
    {
        if (! $this->aktivity->contains($aktivita)) {
            $this->aktivity->add($aktivita);
        }

        return $this;
    }

    public function removeAktivita(Activity $aktivita): self
    {
        $this->aktivity->removeElement($aktivita);

        return $this;
    }

    /**
     * @return Collection<int, TeamMemberRegistration>
     */
    public function getClenove(): Collection
    {
        return $this->clenove;
    }
}
