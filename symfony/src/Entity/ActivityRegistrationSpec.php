<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityRegistrationSpecRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Activity registration specification (special registration state overrides)
 */
#[ORM\Entity(repositoryClass: ActivityRegistrationSpecRepository::class)]
#[ORM\Table(name: 'akce_prihlaseni_spec')]
class ActivityRegistrationSpec extends AbstractActivityRegistration
{
}
