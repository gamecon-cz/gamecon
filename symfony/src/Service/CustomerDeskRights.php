<?php

declare(strict_types=1);

namespace App\Service;

use Gamecon\Pravo;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Who may order on a participant's behalf from the admin desk.
 *
 * Shared by every such endpoint on purpose: they expose the same personal data, and copies of
 * the rule would let one drift into a hole no test catches, because each class would only ever
 * test its own copy.
 *
 * The rights are the ones the two admin screens declare in their module headers, so this is
 * about reaching those screens at all, not about what is being ordered. ROLE_ADMIN cannot
 * stand in for them — it is granted by exact role code, and the codes carrying these rights
 * are per-year (`gc2026_infopult`), so it matches neither reliably.
 */
readonly class CustomerDeskRights
{
    public function __construct(
        private LegacySessionService $legacySession,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Totéž co `verifyOperator()`, jen se ptá místo aby vyhazovalo — pro případy, kdy je
     * obsluha jen jiný pohled na tentýž katalog, ne přístup navíc.
     */
    public function jeObsluhaPultu(): bool
    {
        $operator = $this->legacySession->getCurrentUser();

        return $operator !== null
            && ($operator->maPravo(Pravo::ADMINISTRACE_UBYTOVANI)
                || $operator->maPravo(Pravo::ADMINISTRACE_INFOPULT));
    }

    /**
     * @param string $actionKey translation key in the `errors` domain naming what the operator is doing
     *
     * @throws AccessDeniedHttpException when nobody is signed in, or they may not do this
     */
    public function verifyOperator(string $actionKey): \Uzivatel
    {
        $operator = $this->legacySession->getCurrentUser();
        if ($operator === null) {
            throw new AccessDeniedHttpException($this->translator->trans('desk.admin_login_required', [
                '%action%' => $this->translator->trans($actionKey, [], 'errors'),
            ], 'errors'));
        }

        if (! $operator->maPravo(Pravo::ADMINISTRACE_UBYTOVANI)
            && ! $operator->maPravo(Pravo::ADMINISTRACE_INFOPULT)
        ) {
            throw new AccessDeniedHttpException($this->translator->trans('desk.no_right_to_order', [], 'errors'));
        }

        return $operator;
    }
}
