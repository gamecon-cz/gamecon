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
#[ORM\Index(columns: ['id_reportu', 'id_uzivatele'], name: 'IDX_id_reportu_id_uzivatele')]
class ReportUsageLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Report::class)]
    #[ORM\JoinColumn(name: 'id_reportu', nullable: false, onDelete: 'CASCADE')]
    private Report $report;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_uzivatele', referencedColumnName: 'id_uzivatele', nullable: false, onDelete: 'CASCADE', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private User $usedBy;

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

    public function getReport(): Report
    {
        return $this->report;
    }

    public function setReport(Report $report): self
    {
        $this->report = $report;

        return $this;
    }

    public function getUsedBy(): User
    {
        return $this->usedBy;
    }

    public function setUsedBy(User $usedBy): self
    {
        $this->usedBy = $usedBy;

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
