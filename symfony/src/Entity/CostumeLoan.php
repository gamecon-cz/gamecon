<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gamecon\Kostymy\StavPriVraceniKostymuEnum;

/**
 * Výdej kostýmu a jeho vrácení. Přežije zánik rezervace (odhlášený účastník má kostým pořád u sebe),
 * proto si drží aktivitu, z jejíhož konce se počítá termín vrácení.
 */
#[ORM\Entity]
#[ORM\Table(name: 'kostym_vydej')]
#[ORM\Index(name: 'IDX_id_kostymu_vraceno', columns: ['id_kostymu', 'vraceno'])]
#[ORM\Index(name: 'IDX_id_rezervace', columns: ['id_rezervace'])]
#[ORM\Index(name: 'IDX_id_akce', columns: ['id_akce'])]
#[ORM\Index(name: 'IDX_id_uzivatele', columns: ['id_uzivatele'])]
#[ORM\Index(name: 'IDX_vydal', columns: ['vydal'])]
#[ORM\Index(name: 'IDX_prijal', columns: ['prijal'])]
class CostumeLoan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Costume::class)]
    #[ORM\JoinColumn(name: 'id_kostymu', referencedColumnName: 'id', nullable: false, options: [
        'onUpdate' => 'CASCADE',
    ])]
    private Costume $costume;

    #[ORM\ManyToOne(targetEntity: CostumeReservation::class)]
    #[ORM\JoinColumn(name: 'id_rezervace', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL', options: [
        'onUpdate' => 'CASCADE',
    ])]
    private ?CostumeReservation $reservation = null;

    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'id_akce', referencedColumnName: 'id_akce', nullable: true, onDelete: 'SET NULL', options: [
        'onUpdate' => 'CASCADE',
    ])]
    private ?Activity $activity = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_uzivatele', referencedColumnName: 'id_uzivatele', nullable: false, options: [
        'onUpdate' => 'CASCADE',
    ])]
    private User $uzivatel;

    #[ORM\Column(name: 'vydano', type: Types::DATETIME_MUTABLE, nullable: false)]
    private \DateTimeInterface $vydano;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'vydal', referencedColumnName: 'id_uzivatele', nullable: true, onDelete: 'SET NULL', options: [
        'onUpdate' => 'CASCADE',
    ])]
    private ?User $vydal = null;

    #[ORM\Column(name: 'vraceno', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $vraceno = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'prijal', referencedColumnName: 'id_uzivatele', nullable: true, onDelete: 'SET NULL', options: [
        'onUpdate' => 'CASCADE',
    ])]
    private ?User $prijal = null;

    #[ORM\Column(name: 'stav_pri_vraceni', type: Types::STRING, length: 32, nullable: true, enumType: StavPriVraceniKostymuEnum::class)]
    private ?StavPriVraceniKostymuEnum $stavPriVraceni = null;

    #[ORM\Column(name: 'poznamka', type: Types::TEXT, length: 65535, nullable: false)]
    private string $poznamka = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCostume(): Costume
    {
        return $this->costume;
    }

    public function setCostume(Costume $costume): self
    {
        $this->costume = $costume;

        return $this;
    }

    public function getReservation(): ?CostumeReservation
    {
        return $this->reservation;
    }

    public function setReservation(?CostumeReservation $reservation): self
    {
        $this->reservation = $reservation;

        return $this;
    }

    public function getActivity(): ?Activity
    {
        return $this->activity;
    }

    public function setActivity(?Activity $activity): self
    {
        $this->activity = $activity;

        return $this;
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

    public function getVydano(): \DateTimeInterface
    {
        return $this->vydano;
    }

    public function setVydano(\DateTimeInterface $vydano): self
    {
        $this->vydano = $vydano;

        return $this;
    }

    public function getVydal(): ?User
    {
        return $this->vydal;
    }

    public function setVydal(?User $vydal): self
    {
        $this->vydal = $vydal;

        return $this;
    }

    public function getVraceno(): ?\DateTimeInterface
    {
        return $this->vraceno;
    }

    public function setVraceno(?\DateTimeInterface $vraceno): self
    {
        $this->vraceno = $vraceno;

        return $this;
    }

    public function getPrijal(): ?User
    {
        return $this->prijal;
    }

    public function setPrijal(?User $prijal): self
    {
        $this->prijal = $prijal;

        return $this;
    }

    public function getStavPriVraceni(): ?StavPriVraceniKostymuEnum
    {
        return $this->stavPriVraceni;
    }

    public function setStavPriVraceni(?StavPriVraceniKostymuEnum $stavPriVraceni): self
    {
        $this->stavPriVraceni = $stavPriVraceni;

        return $this;
    }

    public function getPoznamka(): string
    {
        return $this->poznamka;
    }

    public function setPoznamka(string $poznamka): self
    {
        $this->poznamka = $poznamka;

        return $this;
    }
}
