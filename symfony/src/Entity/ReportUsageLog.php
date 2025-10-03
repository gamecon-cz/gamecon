<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReportUsageLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Report usage log (tracks when reports are used)
 */
#[ORM\Entity(repositoryClass: ReportUsageLogRepository::class)]
#[ORM\Table(name: 'reporty_log_pouziti')]
#[ORM\UniqueConstraint(name: 'id', columns: ['id'])]
#[ORM\Index(name: 'report_uzivatel', columns: ['id_reportu', 'id_uzivatele'])]
#[ORM\Index(name: 'id_uzivatele', columns: ['id_uzivatele'])]
class ReportUsageLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\Column(name: 'id_reportu', type: Types::INTEGER, nullable: false, options: [
        'unsigned' => true,
    ])]
    private int $idReportu;

    #[ORM\Column(name: 'id_uzivatele', type: Types::INTEGER, nullable: false)]
    private int $idUzivatele;

    #[ORM\Column(name: 'format', type: Types::STRING, length: 10, nullable: false)]
    private string $format;

    #[ORM\Column(name: 'cas_pouziti', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $casPouziti = null;

    #[ORM\Column(name: 'casova_zona', type: Types::STRING, length: 100, nullable: true)]
    private ?string $casovaZona = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdReportu(): int
    {
        return $this->idReportu;
    }

    public function setIdReportu(int $idReportu): self
    {
        $this->idReportu = $idReportu;

        return $this;
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

    public function getFormat(): string
    {
        return $this->format;
    }

    public function setFormat(string $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function getCasPouziti(): ?\DateTime
    {
        return $this->casPouziti;
    }

    public function setCasPouziti(?\DateTime $casPouziti): self
    {
        $this->casPouziti = $casPouziti;

        return $this;
    }

    public function getCasovaZona(): ?string
    {
        return $this->casovaZona;
    }

    public function setCasovaZona(?string $casovaZona): self
    {
        $this->casovaZona = $casovaZona;

        return $this;
    }
}
