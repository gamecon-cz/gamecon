<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityTagRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Activity tag mapping (many-to-many between activities and unified tags)
 */
#[ORM\Entity(repositoryClass: ActivityTagRepository::class)]
#[ORM\Table(name: 'akce_sjednocene_tagy')]
class ActivityTag
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Activity::class, inversedBy: 'activityTags')]
    #[ORM\JoinColumn(name: 'id_akce', referencedColumnName: 'id_akce', nullable: false, onDelete: 'CASCADE', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private Activity $activity;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Tag::class)]
    #[ORM\JoinColumn(name: 'id_tagu', nullable: false, onDelete: 'CASCADE', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private Tag $tag;

    public function getActivity(): Activity
    {
        return $this->activity;
    }

    public function setActivity(Activity $activity): self
    {
        $this->activity = $activity;

        return $this;
    }

    public function getTag(): Tag
    {
        return $this->tag;
    }

    public function setTag(Tag $tag): self
    {
        $this->tag = $tag;

        return $this;
    }
}
