<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EventLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Event log (general purpose event logging)
 */
#[ORM\Entity(repositoryClass: EventLogRepository::class)]
#[ORM\Table(name: 'log_udalosti')]
#[ORM\UniqueConstraint(name: 'id_udalosti', columns: ['id_udalosti'])]
#[ORM\Index(columns: ['metadata'], name: 'metadata')]
#[ORM\Index(columns: ['id_logujiciho'], name: 'FK_log_udalosti_to_uzivatele_hodnoty')]
class EventLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_udalosti', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $idUdalosti = null;

    #[ORM\Column(name: 'id_logujiciho', type: Types::INTEGER, nullable: false)]
    private int $idLogujiciho;

    #[ORM\Column(name: 'zprava', type: Types::STRING, length: 255, nullable: true)]
    private ?string $zprava = null;

    #[ORM\Column(name: 'metadata', type: Types::STRING, length: 255, nullable: true)]
    private ?string $metadata = null;

    #[ORM\Column(name: 'rok', type: Types::INTEGER, nullable: false, options: [
        'unsigned' => true,
    ])]
    private int $rok;

    public function getIdUdalosti(): ?int
    {
        return $this->idUdalosti;
    }

    public function getIdLogujiciho(): int
    {
        return $this->idLogujiciho;
    }

    public function setIdLogujiciho(int $idLogujiciho): self
    {
        $this->idLogujiciho = $idLogujiciho;

        return $this;
    }

    public function getZprava(): ?string
    {
        return $this->zprava;
    }

    public function setZprava(?string $zprava): self
    {
        $this->zprava = $zprava;

        return $this;
    }

    public function getMetadata(): ?string
    {
        return $this->metadata;
    }

    public function setMetadata(?string $metadata): self
    {
        $this->metadata = $metadata;

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
}
