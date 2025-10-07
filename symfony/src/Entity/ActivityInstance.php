<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityInstanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Activity instance linking to main activity
 */
#[ORM\Entity(repositoryClass: ActivityInstanceRepository::class)]
#[ORM\Table(name: 'akce_instance')]
class ActivityInstance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_instance', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'id_hlavni_akce', referencedColumnName: 'id_akce', nullable: false, onDelete: 'CASCADE', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private Activity $mainActivity;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMainActivity(): Activity
    {
        return $this->mainActivity;
    }

    public function setMainActivity(Activity $mainActivity): self
    {
        $this->mainActivity = $mainActivity;

        return $this;
    }
}
