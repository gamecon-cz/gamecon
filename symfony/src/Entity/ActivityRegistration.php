<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityRegistrationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Activity registration (current state of user's registration to activity)
 */
#[ORM\Entity(repositoryClass: ActivityRegistrationRepository::class)]
#[ORM\Table(name: 'akce_prihlaseni')]
class ActivityRegistration
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'id_akce', referencedColumnName: 'id_akce', nullable: false, options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private Activity $activity;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_uzivatele', referencedColumnName: 'id_uzivatele', nullable: false, options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private User $user;

    #[ORM\ManyToOne(targetEntity: ActivityRegistrationState::class)]
    #[ORM\JoinColumn(name: 'id_stavu_prihlaseni', referencedColumnName: 'id_stavu_prihlaseni', nullable: false, options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private ActivityRegistrationState $activityRegistrationState;

    public function getActivity(): Activity
    {
        return $this->activity;
    }

    public function setActivity(Activity $activity): self
    {
        $this->activity = $activity;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

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
