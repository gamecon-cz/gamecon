<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityOrganizerRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Activity organizer mapping (many-to-many between activities and users)
 */
#[ORM\Entity(repositoryClass: ActivityOrganizerRepository::class)]
#[ORM\Table(name: 'akce_organizatori')]
class ActivityOrganizer
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'id_akce', referencedColumnName: 'id_akce', nullable: false, onDelete: 'CASCADE', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private Activity $activity;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_uzivatele', referencedColumnName: 'id_uzivatele', nullable: false, onDelete: 'CASCADE', options: [
        'ON UPDATE' => 'CASCADE',
        'comment'   => 'organizátor',
    ])]
    private User $user;

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
}
