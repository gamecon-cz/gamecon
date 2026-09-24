<?php

declare(strict_types=1);

namespace App\State\Kfc;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Kfc\KfcProductOutputDto;
use App\Dto\Kfc\KfcProductVariantOutputDto;
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
        // Vrací se celý letošní katalog včetně variant, protože endpoint obsluhuje dvě
        // věci naráz: mřížku pultu, která si podle `cilId` dohledává už nakonfigurovaný
        // předmět, a editor mřížek, kde se předmět do buňky teprve vybírá. Odfiltrovat
        // tu neprodejné znamená, že buňka na ně odkazující zůstane bez názvu i ceny —
        // a protože `zbyva` nevyjde, tváří se jako dostupná a jde na ni kliknout.
        //
        // Ani `stav` se nefiltruje: 34 buněk na živých mřížkách velikostí ukazuje na
        // produkty se stavem MIMO a bez nich by zůstaly prázdné. Co je prodejné, rozhoduje
        // prodej; tenhle endpoint jen říká, co ta buňka je. Archivní produkty proto jdou
        // ven taky, jen označené příznakem, ať si je obě obrazovky umí odlišit.
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT
                shop_predmety.id_predmetu AS id,
                shop_predmety.nazev AS nazev,
                ROUND(shop_predmety.cena_aktualni) AS cena,
                shop_predmety.archived_at IS NOT NULL AS archivni,
                product_variant.id AS varianta_id,
                product_variant.name AS varianta_nazev,
                ROUND(COALESCE(product_variant.price, shop_predmety.cena_aktualni)) AS varianta_cena,
                product_variant.remaining_quantity AS varianta_zbyva
            FROM shop_predmety
            LEFT JOIN product_variant ON product_variant.product_id = shop_predmety.id_predmetu
            ORDER BY shop_predmety.nazev, product_variant.position, product_variant.id
            SQL,
        );

        /** @var array<int, array{dto: array{id: int, name: string, price: int, archived: bool}, variants: KfcProductVariantOutputDto[]}> $podleProduktu */
        $podleProduktu = [];
        foreach ($rows as $row) {
            $idPredmetu = (int) $row['id'];
            if (! isset($podleProduktu[$idPredmetu])) {
                $podleProduktu[$idPredmetu] = [
                    'dto' => [
                        'id'       => $idPredmetu,
                        'name'     => (string) $row['nazev'],
                        'price'    => (int) $row['cena'],
                        'archived' => (bool) $row['archivni'],
                    ],
                    'variants' => [],
                ];
            }

            if ($row['varianta_id'] === null) {
                continue;
            }

            $podleProduktu[$idPredmetu]['variants'][] = new KfcProductVariantOutputDto(
                id: (int) $row['varianta_id'],
                name: (string) $row['varianta_nazev'],
                price: (int) $row['varianta_cena'],
                remaining: $row['varianta_zbyva'] !== null ? (int) $row['varianta_zbyva'] : null,
            );
        }

        return array_values(array_map(
            static function (array $produkt): KfcProductOutputDto {
                $variants = $produkt['variants'];

                return new KfcProductOutputDto(
                    id: $produkt['dto']['id'],
                    name: $produkt['dto']['name'],
                    price: $produkt['dto']['price'],
                    // Jedna varianta = zásoba produktu; u víc jich drží počty varianty samy.
                    remaining: count($variants) === 1 ? $variants[0]->remaining : null,
                    variants: $variants,
                    archived: $produkt['dto']['archived'],
                );
            },
            $podleProduktu,
        ));
    }
}
