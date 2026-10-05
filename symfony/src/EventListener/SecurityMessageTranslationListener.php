<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Lets an operation's `securityMessage` be a key in the `errors` catalogue, like every other
 * message the client shows. API Platform passes it through untranslated; the firewall has
 * already wrapped it in an AccessDeniedHttpException by priority 0.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 0)]
readonly class SecurityMessageTranslationListener
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (! $exception instanceof AccessDeniedHttpException) {
            return;
        }

        $message = $exception->getMessage();
        $translated = $this->translator->trans($message, [], 'errors');
        if ($translated !== $message) {
            $event->setThrowable(new AccessDeniedHttpException($translated, $exception, $exception->getCode(), $exception->getHeaders()));
        }
    }
}
