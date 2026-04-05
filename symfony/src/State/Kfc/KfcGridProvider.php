<?php

declare(strict_types=1);

namespace App\State\Kfc;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Kfc\KfcGridCellOutputDto;
use App\Dto\Kfc\KfcGridOutputDto;
use Doctrine\DBAL\Connection;

/**
 * @implements ProviderInterface<KfcGridOutputDto>
 */
readonly class KfcGridProvider implements ProviderInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @return KfcGridOutputDto[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $grids = $this->connection->fetchAllAssociative(
            'SELECT obchod_mrizky.id, obchod_mrizky.text FROM obchod_mrizky ORDER BY obchod_mrizky.id',
        );

        $cells = $this->connection->fetchAllAssociative(
            'SELECT obchod_bunky.id, obchod_bunky.typ, obchod_bunky.text, obchod_bunky.barva, obchod_bunky.barva_text, obchod_bunky.cil_id, obchod_bunky.mrizka_id FROM obchod_bunky ORDER BY obchod_bunky.id',
        );

        $cellsByGrid = [];
        foreach ($cells as $cell) {
            $gridId = (int) $cell['mrizka_id'];
            $cellsByGrid[$gridId][] = new KfcGridCellOutputDto(
                id: $cell['id'] !== null ? (int) $cell['id'] : null,
                typ: (int) $cell['typ'],
                text: $cell['text'],
                barva: $cell['barva'],
                barvaText: $cell['barva_text'],
                cilId: $cell['cil_id'] !== null ? (int) $cell['cil_id'] : null,
            );
        }

        return array_map(
            static fn (array $grid) => new KfcGridOutputDto(
                id: (int) $grid['id'],
                text: $grid['text'],
                bunky: $cellsByGrid[(int) $grid['id']] ?? [],
            ),
            $grids,
        );
    }
}
