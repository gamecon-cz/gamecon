<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PaymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Payment transaction
 *
 * Legacy @see \Gamecon\Uzivatel\Platba
 */
#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'platby')]
#[ORM\Index(columns: ['id_uzivatele', 'rok'], name: 'IDX_id_uzivatele_rok')]
#[ORM\UniqueConstraint(name: 'UNIQ_fio_id', columns: ['fio_id'])]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_uzivatele', referencedColumnName: 'id_uzivatele', nullable: true, onDelete: 'SET NULL', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private ?User $beneficiary = null;

    #[ORM\Column(name: 'fio_id', type: Types::BIGINT, nullable: true)]
    private ?int $fioId = null;

    #[ORM\Column(name: 'vs', type: Types::STRING, length: 255, nullable: true)]
    private ?string $vs = null;

    #[ORM\Column(name: 'castka', type: Types::DECIMAL, precision: 10, scale: 2, nullable: false)]
    private string $castka;

    #[ORM\Column(name: 'rok', type: Types::SMALLINT, nullable: false)]
    private int $rok;

    #[ORM\Column(name: 'pripsano_na_ucet_banky', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $pripsanoNaUcetBanky = null;

    #[ORM\Column(name: 'provedeno', type: Types::DATETIME_MUTABLE, nullable: false)]
    private \DateTime $provedeno;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'provedl', referencedColumnName: 'id_uzivatele', nullable: false, onDelete: 'RESTRICT', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private User $madeBy;

    #[ORM\Column(name: 'nazev_protiuctu', type: Types::STRING, length: 255, nullable: true)]
    private ?string $nazevProtiuctu = null;

    #[ORM\Column(name: 'cislo_protiuctu', type: Types::STRING, length: 255, nullable: true)]
    private ?string $cisloProtiuctu = null;

    #[ORM\Column(name: 'kod_banky_protiuctu', type: Types::STRING, length: 127, nullable: true)]
    private ?string $kodBankyProtiuctu = null;

    #[ORM\Column(name: 'nazev_banky_protiuctu', type: Types::STRING, length: 255, nullable: true)]
    private ?string $nazevBankyProtiuctu = null;

    #[ORM\Column(name: 'poznamka', type: Types::TEXT, nullable: true)]
    private ?string $poznamka = null;

    #[ORM\Column(name: 'skryta_poznamka', type: Types::TEXT, nullable: true)]
    private ?string $skrytaPoznamka = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBeneficiary(): ?User
    {
        return $this->beneficiary;
    }

    public function setBeneficiary(?User $beneficiary): self
    {
        $this->beneficiary = $beneficiary;

        return $this;
    }

    public function getFioId(): ?int
    {
        return $this->fioId;
    }

    public function setFioId(?int $fioId): self
    {
        $this->fioId = $fioId;

        return $this;
    }

    public function getVs(): ?string
    {
        return $this->vs;
    }

    public function setVs(?string $vs): self
    {
        $this->vs = $vs;

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

    public function getPripsanoNaUcetBanky(): ?\DateTime
    {
        return $this->pripsanoNaUcetBanky;
    }

    public function setPripsanoNaUcetBanky(?\DateTime $pripsanoNaUcetBanky): self
    {
        $this->pripsanoNaUcetBanky = $pripsanoNaUcetBanky;

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

    public function getMadeBy(): User
    {
        return $this->madeBy;
    }

    public function setMadeBy(User $madeBy): self
    {
        $this->madeBy = $madeBy;

        return $this;
    }

    public function getNazevProtiuctu(): ?string
    {
        return $this->nazevProtiuctu;
    }

    public function setNazevProtiuctu(?string $nazevProtiuctu): self
    {
        $this->nazevProtiuctu = $nazevProtiuctu;

        return $this;
    }

    public function getCisloProtiuctu(): ?string
    {
        return $this->cisloProtiuctu;
    }

    public function setCisloProtiuctu(?string $cisloProtiuctu): self
    {
        $this->cisloProtiuctu = $cisloProtiuctu;

        return $this;
    }

    public function getKodBankyProtiuctu(): ?string
    {
        return $this->kodBankyProtiuctu;
    }

    public function setKodBankyProtiuctu(?string $kodBankyProtiuctu): self
    {
        $this->kodBankyProtiuctu = $kodBankyProtiuctu;

        return $this;
    }

    public function getNazevBankyProtiuctu(): ?string
    {
        return $this->nazevBankyProtiuctu;
    }

    public function setNazevBankyProtiuctu(?string $nazevBankyProtiuctu): self
    {
        $this->nazevBankyProtiuctu = $nazevBankyProtiuctu;

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

    public function getSkrytaPoznamka(): ?string
    {
        return $this->skrytaPoznamka;
    }

    public function setSkrytaPoznamka(?string $skrytaPoznamka): self
    {
        $this->skrytaPoznamka = $skrytaPoznamka;

        return $this;
    }
}
