<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\PriceIncreaseNotifier;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * postFlush is dispatched after the commit, unlike postPersist — so a price increase queued
 * during the transaction is reported only once it is actually stored, and a mail failure
 * cannot roll the change back.
 */
#[AsDoctrineListener(event: Events::postFlush)]
readonly class PriceIncreaseFlushListener
{
    public function __construct(
        private PriceIncreaseNotifier $priceIncreaseNotifier,
    ) {
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $this->priceIncreaseNotifier->odesliFrontu();
    }
}
