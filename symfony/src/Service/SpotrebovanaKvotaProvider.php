<?php

declare(strict_types=1);

namespace App\Service;

use App\Discount\DiscountRuleLoader;
use App\Discount\HodnotyNastaveniSlev;
use App\Discount\SpotrebovanaKvota;
use App\Entity\User;
use App\Repository\OrderItemRepository;

/**
 * Kolik z kvóty každého pravidla zákazník letos utratil.
 *
 * Nezapamatovává si nic. Košík mezi dvěma dotazy zapisuje — po přidání kostky je nárok
 * pryč a druhá kostka už zdarma není. Zapamatovaná hodnota tu změnu zamlčí a naúčtuje
 * nulu i podruhé; přesně to je chyba, kvůli které tahle třída vznikla.
 *
 * Mřížka se ptá jednou za produkt, takže ji to stojí dotaz navíc na produkt. Cachovat
 * půjde až s invalidací vázanou na zápis do košíku.
 */
class SpotrebovanaKvotaProvider
{
    public function __construct(
        private readonly OrderItemRepository $orderItemRepository,
        private readonly DiscountRuleLoader $ruleLoader,
    ) {
    }

    /**
     * @return array<string, int> kód pravidla → kolik kusů kvóty padlo
     */
    public function pro(User $user, int $year): array
    {
        $idUzivatele = $user->getId();
        if ($idUzivatele === null) {
            return [];
        }

        return SpotrebovanaKvota::zNakupu(
            $this->ruleLoader->rulesForYear($year),
            $this->orderItemRepository->discountableCustomerPurchases($user, $year),
            $this->ruleLoader->rightsOfUser($idUzivatele, $year),
            HodnotyNastaveniSlev::z(),
        );
    }
}
