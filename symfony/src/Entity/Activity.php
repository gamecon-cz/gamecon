<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\StavAktivity;

/**
 * Activity (main activity/event entity)
 * Legacy @see Aktivita
 */
#[ORM\Entity(repositoryClass: ActivityRepository::class)]
#[ORM\Table(name: 'akce_seznam')]
#[ORM\UniqueConstraint(name: 'UNIQ_url_akce_rok_typ', columns: ['url_akce', 'rok', 'typ'])]
#[ORM\Index(name: 'IDX_rok', columns: ['rok'])]
class Activity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_akce', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ActivityInstance::class)]
    #[ORM\JoinColumn(name: 'patri_pod', referencedColumnName: 'id_instance', nullable: true, onDelete: 'SET NULL')]
    private ?ActivityInstance $activityInstance = null;

    #[ORM\Column(name: 'nazev_akce', type: Types::STRING, length: 255, nullable: false)]
    private string $nazevAkce;

    #[ORM\Column(name: 'url_akce', type: Types::STRING, length: 64, nullable: true)]
    private ?string $urlAkce = null;

    #[ORM\Column(name: 'zacatek', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $zacatek = null;

    #[ORM\Column(name: 'konec', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $konec = null;

    /**
     * @var Collection<int, Location>
     */
    #[ORM\ManyToMany(targetEntity: Location::class)]
    #[ORM\JoinTable(name: 'akce_lokace')]
    #[ORM\JoinColumn(name: 'id_akce', referencedColumnName: 'id_akce', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'id_lokace', referencedColumnName: 'id_lokace', onDelete: 'CASCADE')]
    private Collection $locations;

    #[ORM\Column(name: 'kapacita', type: Types::INTEGER, nullable: false)]
    private int $kapacita;

    #[ORM\Column(name: 'kapacita_f', type: Types::INTEGER, nullable: false)]
    private int $kapacitaF;

    #[ORM\Column(name: 'kapacita_m', type: Types::INTEGER, nullable: false)]
    private int $kapacitaM;

    #[ORM\Column(name: 'cena', type: Types::INTEGER, nullable: false)]
    private int $cena;

    #[ORM\Column(name: 'bez_slevy', type: Types::BOOLEAN, nullable: false, options: [
        'comment' => 'na aktivitu se neuplatňují slevy',
    ])]
    private bool $bezSlevy;

    #[ORM\Column(name: 'nedava_bonus', type: Types::BOOLEAN, nullable: false, options: [
        'comment' => 'aktivita negeneruje organizátorovi bonus za vedení aktivity',
    ])]
    private bool $nedavaBonus;

    #[ORM\ManyToOne(targetEntity: ActivityType::class)]
    #[ORM\JoinColumn(name: 'typ', referencedColumnName: 'id_typu', nullable: false, onDelete: 'RESTRICT')]
    private ActivityType $type;

    #[ORM\ManyToOne(targetEntity: Tournament::class, inversedBy: 'aktivity')]
    #[ORM\JoinColumn(name: 'id_turnaje', referencedColumnName: 'id_turnaje', nullable: true, onDelete: 'SET NULL')]
    private ?Tournament $tournament = null;

    #[ORM\Column(name: 'turnaj_kolo', type: Types::SMALLINT, nullable: true, options: [
        'unsigned' => true,
        'comment'  => 'číslo kola v turnaji (1 = první kolo)',
    ])]
    private ?int $turnajKolo = null;

    #[ORM\Column(name: 'rok', type: Types::INTEGER, nullable: false)]
    private int $rok;

    #[ORM\ManyToOne(targetEntity: ActivityStatus::class)]
    #[ORM\JoinColumn(name: 'stav', referencedColumnName: 'id_stav', nullable: false, onDelete: 'RESTRICT', options: [
        'default' => StavAktivity::NOVA,
    ])]
    private ActivityStatus $status;

    #[ORM\Column(name: 'teamova', type: Types::BOOLEAN, nullable: false)]
    private bool $teamova;

    #[ORM\Column(name: 'team_min', type: Types::INTEGER, nullable: true, options: [
        'comment' => 'minimální velikost teamu',
    ])]
    private ?int $teamMin = null;

    #[ORM\Column(name: 'team_max', type: Types::INTEGER, nullable: true, options: [
        'comment' => 'maximální velikost teamu',
    ])]
    private ?int $teamMax = null;

    #[ORM\Column(name: 'team_kapacita', type: Types::INTEGER, nullable: true, options: [
        'comment' => 'max. počet týmů na aktivitě',
    ])]
    private ?int $teamKapacita = null;

    #[ORM\Column(name: 'tym_smazat_po_expiraci', type: Types::BOOLEAN, nullable: false, options: [
        'default' => 0,
        'comment' => 'po expiraci rozpracovaného týmu: true = smazat, false = zveřejnit',
    ])]
    private bool $tymSmazatPoExpiraci = true;

    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(name: 'id_hlavni_lokace', referencedColumnName: 'id_lokace', nullable: true, onDelete: 'SET NULL')]
    private ?Location $mainLocation = null;

    #[ORM\Column(name: 'popis', type: Types::TEXT, nullable: false, options: [
        'comment' => 'markdown',
    ])]
    private string $description;

    #[ORM\Column(name: 'popis_kratky', type: Types::STRING, length: 255, nullable: false)]
    private string $shortDescription;

    #[ORM\Column(name: 'vybaveni', type: Types::TEXT, nullable: false)]
    private string $vybaveni;

    #[ORM\Column(name: 'probehla_korekce', type: Types::BOOLEAN, nullable: false, options: [
        'default' => 0,
    ])]
    private bool $probehlaKorekce = false;

    /**
     * @var Collection<int, ActivityTag>
     */
    #[ORM\OneToMany(targetEntity: ActivityTag::class, mappedBy: 'activity', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $activityTags;

    public function __construct()
    {
        $this->locations = new ArrayCollection();
        $this->activityTags = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActivityInstance(): ?ActivityInstance
    {
        return $this->activityInstance;
    }

    public function setActivityInstance(?ActivityInstance $activityInstance): self
    {
        $this->activityInstance = $activityInstance;

        return $this;
    }

    public function getNazevAkce(): string
    {
        return $this->nazevAkce;
    }

    public function setNazevAkce(string $nazevAkce): self
    {
        $this->nazevAkce = $nazevAkce;

        return $this;
    }

    public function getUrlAkce(): ?string
    {
        return $this->urlAkce;
    }

    public function setUrlAkce(?string $urlAkce): self
    {
        $this->urlAkce = $urlAkce;

        return $this;
    }

    public function getZacatek(): ?\DateTime
    {
        return $this->zacatek;
    }

    public function setZacatek(?\DateTime $zacatek): self
    {
        $this->zacatek = $zacatek;

        return $this;
    }

    public function getKonec(): ?\DateTime
    {
        return $this->konec;
    }

    public function setKonec(?\DateTime $konec): self
    {
        $this->konec = $konec;

        return $this;
    }

    /**
     * @return Collection<int, Location>
     */
    public function getLocations(): Collection
    {
        return $this->locations;
    }

    public function addLocation(Location $location): self
    {
        if (! $this->locations->contains($location)) {
            $this->locations->add($location);
        }

        return $this;
    }

    public function removeLocation(Location $location): self
    {
        $this->locations->removeElement($location);

        return $this;
    }

    public function getKapacita(): int
    {
        return $this->kapacita;
    }

    public function setKapacita(int $kapacita): self
    {
        $this->kapacita = $kapacita;

        return $this;
    }

    public function getKapacitaF(): int
    {
        return $this->kapacitaF;
    }

    public function setKapacitaF(int $kapacitaF): self
    {
        $this->kapacitaF = $kapacitaF;

        return $this;
    }

    public function getKapacitaM(): int
    {
        return $this->kapacitaM;
    }

    public function setKapacitaM(int $kapacitaM): self
    {
        $this->kapacitaM = $kapacitaM;

        return $this;
    }

    public function getCena(): int
    {
        return $this->cena;
    }

    public function setCena(int $cena): self
    {
        $this->cena = $cena;

        return $this;
    }

    public function getBezSlevy(): bool
    {
        return $this->bezSlevy;
    }

    public function setBezSlevy(bool $bezSlevy): self
    {
        $this->bezSlevy = $bezSlevy;

        return $this;
    }

    public function getNedavaBonus(): bool
    {
        return $this->nedavaBonus;
    }

    public function setNedavaBonus(bool $nedavaBonus): self
    {
        $this->nedavaBonus = $nedavaBonus;

        return $this;
    }

    public function getType(): ActivityType
    {
        return $this->type;
    }

    public function setType(ActivityType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getTournament(): ?Tournament
    {
        return $this->tournament;
    }

    public function setTournament(?Tournament $tournament): self
    {
        $this->tournament = $tournament;

        return $this;
    }

    public function getTurnajKolo(): ?int
    {
        return $this->turnajKolo;
    }

    public function setTurnajKolo(?int $turnajKolo): self
    {
        $this->turnajKolo = $turnajKolo;

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

    public function getStatus(): ActivityStatus
    {
        return $this->status;
    }

    public function setStatus(ActivityStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getTeamova(): bool
    {
        return $this->teamova;
    }

    public function setTeamova(bool $teamova): self
    {
        $this->teamova = $teamova;

        return $this;
    }

    public function getTeamMin(): ?int
    {
        return $this->teamMin;
    }

    public function setTeamMin(?int $teamMin): self
    {
        $this->teamMin = $teamMin;

        return $this;
    }

    public function getTeamMax(): ?int
    {
        return $this->teamMax;
    }

    public function setTeamMax(?int $teamMax): self
    {
        $this->teamMax = $teamMax;

        return $this;
    }

    public function getTeamKapacita(): ?int
    {
        return $this->teamKapacita;
    }

    public function setTeamKapacita(?int $teamKapacita): self
    {
        $this->teamKapacita = $teamKapacita;

        return $this;
    }

    public function isTymSmazatPoExpiraci(): bool
    {
        return $this->tymSmazatPoExpiraci;
    }

    public function setTymSmazatPoExpiraci(bool $tymSmazatPoExpiraci): self
    {
        $this->tymSmazatPoExpiraci = $tymSmazatPoExpiraci;

        return $this;
    }

    public function getMainLocation(): ?Location
    {
        return $this->mainLocation;
    }

    public function setMainLocation(?Location $mainLocation): self
    {
        $this->mainLocation = $mainLocation;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getShortDescription(): string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(string $shortDescription): self
    {
        $this->shortDescription = $shortDescription;

        return $this;
    }

    public function getVybaveni(): string
    {
        return $this->vybaveni;
    }

    public function setVybaveni(string $vybaveni): self
    {
        $this->vybaveni = $vybaveni;

        return $this;
    }

    public function getProbehlaKorekce(): bool
    {
        return $this->probehlaKorekce;
    }

    public function setProbehlaKorekce(bool $probehlaKorekce): self
    {
        $this->probehlaKorekce = $probehlaKorekce;

        return $this;
    }

    /**
     * @return Collection<int, ActivityTag>
     */
    public function getActivityTags(): Collection
    {
        return $this->activityTags;
    }

    public function addActivityTag(ActivityTag $activityTag): self
    {
        if (! $this->activityTags->contains($activityTag)) {
            $this->activityTags->add($activityTag);
            $activityTag->setActivity($this);
        }

        return $this;
    }

    public function removeActivityTag(ActivityTag $activityTag): self
    {
        $this->activityTags->removeElement($activityTag);

        return $this;
    }
}
