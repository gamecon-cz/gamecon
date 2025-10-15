<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\NewsletterSubscriptionLog
 */
class NewsletterSubscriptionLogSqlStructure
{
    /**
     * @see NewsletterSubscriptionLog
     */
    public const _table = 'newsletter_prihlaseni_log';

    /**
     * @see NewsletterSubscriptionLog::$id
     */
    public const id_newsletter_prihlaseni_log = 'id_newsletter_prihlaseni_log';

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
