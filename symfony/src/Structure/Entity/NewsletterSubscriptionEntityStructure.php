<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\NewsletterSubscription
 */
class NewsletterSubscriptionEntityStructure
{
    /**
     * @see NewsletterSubscription::$id
     */
    public const id = 'id';

    /**
     * @see NewsletterSubscription::$email
     */
    public const email = 'email';

    /**
     * @see NewsletterSubscription::$kdy
     */
    public const kdy = 'kdy';
}
