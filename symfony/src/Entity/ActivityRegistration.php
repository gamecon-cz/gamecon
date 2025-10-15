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
class ActivityRegistration extends AbstractActivityRegistration
{
}
