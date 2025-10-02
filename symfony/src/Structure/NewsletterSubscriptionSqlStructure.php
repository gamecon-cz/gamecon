<?php

declare(strict_types=1);

namespace App\Structure;

/**
 * Structure for @see \App\Entity\NewsletterSubscription
 * SQL table `newsletter_prihlaseni`
 */
class NewsletterSubscriptionSqlStructure
{
    public const ID = 'id_newsletter_prihlaseni';
    public const EMAIL = 'email';
    public const KDY = 'kdy';
}
