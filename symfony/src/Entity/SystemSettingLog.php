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
class SystemSettingLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_nastaveni_log', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_uzivatele', referencedColumnName: 'id_uzivatele', nullable: true, onDelete: 'SET NULL', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private ?User $changedBy = null;

    #[ORM\ManyToOne(targetEntity: SystemSetting::class)]
    #[ORM\JoinColumn(name: 'id_nastaveni', referencedColumnName: 'id_nastaveni', nullable: false, onDelete: 'CASCADE', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private SystemSetting $systemSetting;

    #[ORM\Column(name: 'hodnota', type: Types::STRING, length: 256, nullable: true)]
    private ?string $hodnota = null;

    #[ORM\Column(name: 'vlastni', type: Types::BOOLEAN, nullable: true)]
    private ?bool $vlastni = null;

    #[ORM\Column(name: 'kdy', type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTime $kdy;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChangedBy(): ?User
    {
        return $this->changedBy;
    }

    public function setChangedBy(?User $changedBy): self
    {
        $this->changedBy = $changedBy;

        return $this;
    }

    public function getSystemSetting(): SystemSetting
    {
        return $this->systemSetting;
    }

    public function setSystemSetting(SystemSetting $systemSetting): self
    {
        $this->systemSetting = $systemSetting;

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
