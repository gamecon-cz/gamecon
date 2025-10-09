<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityRegistrationSpecRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Activity registration specification (special registration state overrides)
 */
#[ORM\Entity(repositoryClass: ActivityRegistrationSpecRepository::class)]
#[ORM\Table(name: 'akce_prihlaseni_spec')]
#[ORM\UniqueConstraint(name: 'UNIQ_id_akce_id_uzivatele', columns: ['id_akce', 'id_uzivatele'])]
class ActivityRegistrationSpec
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'id_akce', referencedColumnName: 'id_akce', nullable: false, options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private Activity $activity;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_uzivatele', referencedColumnName: 'id_uzivatele', nullable: false, options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private User $registeredUser;

    #[ORM\ManyToOne(targetEntity: ActivityRegistrationState::class)]
    #[ORM\JoinColumn(name: 'id_stavu_prihlaseni', referencedColumnName: 'id_stavu_prihlaseni', nullable: false, options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private ActivityRegistrationState $activityRegistrationState;

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

    public function getActivityRegistrationState(): ActivityRegistrationState
    {
        return $this->activityRegistrationState;
    }

    public function setActivityRegistrationState(ActivityRegistrationState $activityRegistrationState): self
    {
        $this->activityRegistrationState = $activityRegistrationState;

        return $this;
    }
}
