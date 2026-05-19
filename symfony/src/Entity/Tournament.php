<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TournamentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TournamentRepository::class)]
#[ORM\Table(name: 'turnaje')]
#[ORM\Index(name: 'IDX_rok', columns: ['rok'])]
class Tournament
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_turnaje', type: Types::INTEGER, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\Column(name: 'nazev', type: Types::STRING, length: 255, nullable: false)]
    private string $nazev;

    #[ORM\Column(name: 'rok', type: Types::SMALLINT, nullable: false, options: [
        'unsigned' => true,
    ])]
    private int $rok;

    /**
     * @var Collection<int, Activity>
     */
    #[ORM\OneToMany(targetEntity: Activity::class, mappedBy: 'tournament')]
    private Collection $aktivity;

    public function __construct()
    {
        $this->aktivity = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNazev(): string
    {
        return $this->nazev;
    }

    public function setNazev(string $nazev): self
    {
        $this->nazev = $nazev;

        return $this;
    }

    public function getRok(): int
    {
        return $this->rok;
    }

    public function setRok(int $rok): self
    {
        $this->rok = $rok;

        return $this;
    }

    /**
     * @return Collection<int, Activity>
     */
    public function getAktivity(): Collection
    {
        return $this->aktivity;
    }
}
