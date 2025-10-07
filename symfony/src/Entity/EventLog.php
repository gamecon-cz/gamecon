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
#[ORM\Index(columns: ['metadata'], name: 'IDX_metadata')]
class EventLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_udalosti', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_logujiciho', referencedColumnName: 'id_uzivatele', nullable: false, onDelete: 'CASCADE', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private User $loggedBy;

    #[ORM\Column(name: 'zprava', type: Types::STRING, length: 255, nullable: true)]
    private ?string $zprava = null;

    #[ORM\Column(name: 'metadata', type: Types::STRING, length: 255, nullable: true)]
    private ?string $metadata = null;

    #[ORM\Column(name: 'rok', type: Types::INTEGER, nullable: false, options: [
        'unsigned' => true,
    ])]
    private int $rok;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLoggedBy(): User
    {
        return $this->loggedBy;
    }

    public function setLoggedBy(User $loggedBy): self
    {
        $this->loggedBy = $loggedBy;

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
