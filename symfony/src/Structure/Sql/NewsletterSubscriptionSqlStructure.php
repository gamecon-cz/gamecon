<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\NewsletterSubscription
 */
class NewsletterSubscriptionSqlStructure
{
    /**
     * @see NewsletterSubscription
     */
    public const _table = 'newsletter_prihlaseni';

    /**
     * @see NewsletterSubscription::$id
     */
    public const id_newsletter_prihlaseni = 'id_newsletter_prihlaseni';

    /**
     * @see NewsletterSubscription::$email
     */
    public const email = 'email';

    /**
     * @see NewsletterSubscription::$kdy
     */
    public const kdy = 'kdy';
}
