<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SystemSettingLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * System setting log (history of setting changes)
 */
#[ORM\Entity(repositoryClass: SystemSettingLogRepository::class)]
#[ORM\Table(name: 'systemove_nastaveni_log')]
#[ORM\UniqueConstraint(name: 'id_nastaveni_log', columns: ['id_nastaveni_log'])]
#[ORM\Index(columns: ['id_nastaveni'], name: 'FK_systemove_nastaveni_log_to_systemove_nastaveni')]
#[ORM\Index(columns: ['id_uzivatele'], name: 'FK_systemove_nastaveni_log_to_uzivatele_hodnoty')]
class SystemSettingLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_nastaveni_log', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $idNastaveniLog = null;

    #[ORM\Column(name: 'id_uzivatele', type: Types::INTEGER, nullable: true)]
    private ?int $idUzivatele = null;

    #[ORM\Column(name: 'id_nastaveni', type: Types::BIGINT, nullable: false, options: [
        'unsigned' => true,
    ])]
    private int $idNastaveni;

    #[ORM\Column(name: 'hodnota', type: Types::STRING, length: 256, nullable: true)]
    private ?string $hodnota = null;

    #[ORM\Column(name: 'vlastni', type: Types::BOOLEAN, nullable: true)]
    private ?bool $vlastni = null;

    #[ORM\Column(name: 'kdy', type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTime $kdy;

    public function getIdNastaveniLog(): ?int
    {
        return $this->idNastaveniLog;
    }

    public function getIdUzivatele(): ?int
    {
        return $this->idUzivatele;
    }

    public function setIdUzivatele(?int $idUzivatele): self
    {
        $this->idUzivatele = $idUzivatele;

        return $this;
    }

    public function getIdNastaveni(): int
    {
        return $this->idNastaveni;
    }

    public function setIdNastaveni(int $idNastaveni): self
    {
        $this->idNastaveni = $idNastaveni;

        return $this;
    }

    public function getHodnota(): ?string
    {
        return $this->hodnota;
    }

    public function setHodnota(?string $hodnota): self
    {
        $this->hodnota = $hodnota;

        return $this;
    }

    public function getVlastni(): ?bool
    {
        return $this->vlastni;
    }

    public function setVlastni(?bool $vlastni): self
    {
        $this->vlastni = $vlastni;

        return $this;
    }

    public function getKdy(): \DateTime
    {
        return $this->kdy;
    }

    public function setKdy(\DateTime $kdy): self
    {
        $this->kdy = $kdy;

        return $this;
    }
}
