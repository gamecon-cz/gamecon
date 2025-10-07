<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DiscountRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Discount (manual discount applied to user)
 */
#[ORM\Entity(repositoryClass: DiscountRepository::class)]
#[ORM\Table(name: 'slevy')]
class Discount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_uzivatele', referencedColumnName: 'id_uzivatele', nullable: false, onDelete: 'CASCADE', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private User $user;

    #[ORM\Column(name: 'castka', type: Types::DECIMAL, precision: 10, scale: 2, nullable: false)]
    private string $castka;

    #[ORM\Column(name: 'rok', type: Types::INTEGER, nullable: false)]
    private int $rok;

    #[ORM\Column(name: 'provedeno', type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTime $provedeno;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'provedl', referencedColumnName: 'id_uzivatele', nullable: true, onDelete: 'SET NULL', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private ?User $madeBy = null;

    #[ORM\Column(name: 'poznamka', type: Types::TEXT, nullable: true)]
    private ?string $poznamka = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getCastka(): string
    {
        return $this->castka;
    }

    public function setCastka(string $castka): self
    {
        $this->castka = $castka;

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

    public function getProvedeno(): \DateTime
    {
        return $this->provedeno;
    }

    public function setProvedeno(\DateTime $provedeno): self
    {
        $this->provedeno = $provedeno;

        return $this;
    }

    public function getMadeBy(): ?User
    {
        return $this->madeBy;
    }

    public function setMadeBy(?User $madeBy): self
    {
        $this->madeBy = $madeBy;

        return $this;
    }

    public function getPoznamka(): ?string
    {
        return $this->poznamka;
    }

    public function setPoznamka(?string $poznamka): self
    {
        $this->poznamka = $poznamka;

        return $this;
    }
}
