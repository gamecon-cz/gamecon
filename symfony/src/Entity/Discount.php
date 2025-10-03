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
#[ORM\Index(name: 'id_uzivatele', columns: ['id_uzivatele'])]
#[ORM\Index(name: 'provedl', columns: ['provedl'])]
#[ORM\UniqueConstraint(name: 'PRIMARY', columns: ['id'])]
class Discount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'id_uzivatele', type: Types::INTEGER, nullable: false)]
    private int $idUzivatele;

    #[ORM\Column(name: 'castka', type: Types::DECIMAL, precision: 10, scale: 2, nullable: false)]
    private string $castka;

    #[ORM\Column(name: 'rok', type: Types::INTEGER, nullable: false)]
    private int $rok;

    #[ORM\Column(name: 'provedeno', type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTime $provedeno;

    #[ORM\Column(name: 'provedl', type: Types::INTEGER, nullable: true)]
    private ?int $provedl = null;

    #[ORM\Column(name: 'poznamka', type: Types::TEXT, nullable: true)]
    private ?string $poznamka = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdUzivatele(): int
    {
        return $this->idUzivatele;
    }

    public function setIdUzivatele(int $idUzivatele): self
    {
        $this->idUzivatele = $idUzivatele;

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

    public function getProvedl(): ?int
    {
        return $this->provedl;
    }

    public function setProvedl(?int $provedl): self
    {
        $this->provedl = $provedl;

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
