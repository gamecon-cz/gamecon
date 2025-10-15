<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\NewsletterSubscriptionLog
 */
class NewsletterSubscriptionLogEntityStructure
{
    /**
     * @see NewsletterSubscriptionLog::$id
     */
    public const id = 'id';

    /**
     * @see NewsletterSubscriptionLog::$email
     */
    public const email = 'email';

    /**
     * @see NewsletterSubscriptionLog::$kdy
     */
    public const kdy = 'kdy';

    /**
     * @see NewsletterSubscriptionLog::$stav
     */
    public const stav = 'stav';
}
