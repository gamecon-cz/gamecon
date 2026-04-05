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
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT
                shop_predmety.id_predmetu AS id,
                CONCAT(shop_predmety.nazev, ' ', shop_predmety_s_typem.model_rok) AS nazev,
                ROUND(shop_predmety.cena_aktualni) AS cena,
                shop_predmety.kusu_vyrobeno - (
                    SELECT COUNT(*) FROM shop_nakupy
                    WHERE shop_nakupy.id_predmetu = shop_predmety.id_predmetu
                ) AS zbyva
            FROM shop_predmety
            JOIN shop_predmety_s_typem ON shop_predmety.id_predmetu = shop_predmety_s_typem.id_predmetu
            WHERE shop_predmety.stav > 0
            ORDER BY shop_predmety_s_typem.model_rok DESC, shop_predmety.nazev
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
