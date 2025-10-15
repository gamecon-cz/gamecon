<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'akce_stavy_log')]
class ActivityStatusLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'akce_stavy_log_id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'id_akce', referencedColumnName: 'id_akce', nullable: false, onDelete: 'CASCADE', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private Activity $activity;

    #[ORM\ManyToOne(targetEntity: ActivityStatus::class)]
    #[ORM\JoinColumn(name: 'id_stav', referencedColumnName: 'id_stav', nullable: false, onDelete: 'CASCADE')]
    private ActivityStatus $status;

    #[ORM\Column(name: 'kdy', type: Types::DATETIME_IMMUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTimeImmutable $kdy;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActivity(): Activity
    {
        return $this->activity;
    }

    public function setActivity(Activity $activity): self
    {
        $this->activity = $activity;

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

    public function getKdy(): \DateTimeImmutable
    {
        return $this->kdy;
    }

    public function setKdy(\DateTimeImmutable $kdy): self
    {
        $this->kdy = $kdy;

        return $this;
    }
}
