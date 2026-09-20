<?php

declare(strict_types=1);

namespace App\State\Kfc;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Kfc\KfcProductOutputDto;
use Doctrine\DBAL\Connection;

/**
 * @implements ProviderInterface<KfcProductOutputDto>
 */
readonly class KfcProductsProvider implements ProviderInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @return KfcProductOutputDto[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        // Zásoba se čte z varianty, protože právě `remaining_quantity` vynucuje prodej
        // (`CapacityManager::purchase()`). Dopočítávat ji z `kusu_vyrobeno` minus nákupy
        // dávalo jiné číslo než to, které pultu prodej povolí — u letošní kostky 139
        // proti 500. Ročník tu proto být nemusí; varianta ho nezná a nepotřebuje.
        //
        // Pult umí prodat jen jednoznačný předmět (viz `KfcSaleProcessor`), takže se
        // nabízejí jen produkty s právě jednou variantou — u víc variant by prodej stejně
        // skončil chybou. Archivní produkty do letošní nabídky nepatří.
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT
                shop_predmety.id_predmetu AS id,
                shop_predmety.nazev AS nazev,
                ROUND(shop_predmety.cena_aktualni) AS cena,
                product_variant.remaining_quantity AS zbyva
            FROM shop_predmety
            JOIN product_variant ON product_variant.product_id = shop_predmety.id_predmetu
            WHERE shop_predmety.stav > 0
              AND shop_predmety.archived_at IS NULL
              AND (
                  SELECT COUNT(*) FROM product_variant AS vsechny
                  WHERE vsechny.product_id = shop_predmety.id_predmetu
              ) = 1
            ORDER BY shop_predmety.nazev
            SQL,
        );

        return array_map(
            static fn (array $row) => new KfcProductOutputDto(
                id: (int) $row['id'],
                name: (string) $row['nazev'],
                price: (int) $row['cena'],
                remaining: $row['zbyva'] !== null ? (int) $row['zbyva'] : null,
            ),
            $rows,
        );
    }
}
