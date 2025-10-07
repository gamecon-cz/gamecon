<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityRegistrationLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Activity registration log (history of registration state changes)
 */
#[ORM\Entity(repositoryClass: ActivityRegistrationLogRepository::class)]
#[ORM\Table(name: 'akce_prihlaseni_log')]
#[ORM\Index(columns: ['typ'], name: 'IDX_typ')]
#[ORM\Index(columns: ['zdroj_zmeny'], name: 'IDX_zdroj_zmeny')]
class ActivityRegistrationLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_log', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'id_akce', referencedColumnName: 'id_akce', nullable: false, onDelete: 'CASCADE', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private Activity $activity;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_uzivatele', referencedColumnName: 'id_uzivatele', nullable: false, onDelete: 'CASCADE', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private User $registeredUser;

    #[ORM\Column(name: 'kdy', type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTime $kdy;

    #[ORM\Column(name: 'typ', type: Types::STRING, length: 64, nullable: true)]
    private ?string $typ = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_zmenil', referencedColumnName: 'id_uzivatele', nullable: true, onDelete: 'SET NULL', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private ?User $changedBy = null;

    #[ORM\Column(name: 'zdroj_zmeny', type: Types::STRING, length: 128, nullable: true)]
    private ?string $zdrojZmeny = null;

    #[ORM\Column(name: 'rocnik', type: Types::INTEGER, nullable: true, options: [
        'unsigned' => true,
    ])]
    private ?int $rocnik = null;

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

    public function getRegisteredUser(): User
    {
        return $this->registeredUser;
    }

    public function setRegisteredUser(User $registeredUser): self
    {
        $this->registeredUser = $registeredUser;

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

    public function getTyp(): ?string
    {
        return $this->typ;
    }

    public function setTyp(?string $typ): self
    {
        $this->typ = $typ;

        return $this;
    }

    public function getChangedBy(): ?int
    {
        return $this->changedBy;
    }

    public function setChangedBy(?int $changedBy): self
    {
        $this->changedBy = $changedBy;

        return $this;
    }

    public function getZdrojZmeny(): ?string
    {
        return $this->zdrojZmeny;
    }

    public function setZdrojZmeny(?string $zdrojZmeny): self
    {
        $this->zdrojZmeny = $zdrojZmeny;

        return $this;
    }

    public function getRocnik(): ?int
    {
        return $this->rocnik;
    }

    public function setRocnik(?int $rocnik): self
    {
        $this->rocnik = $rocnik;

        return $this;
    }
}
