<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\EventSubscriber;

use Doctrine\Common\EventSubscriber;

/** @deprecated use the {@see AsDoctrineListener} attribute instead */
interface EventSubscriberInterface extends EventSubscriber
{
}
