<?php

declare(strict_types=1);

namespace App\Tests\State\Kfc;

use ApiPlatform\Metadata\Post;
use App\Dto\Kfc\KfcGridCellInputDto;
use App\Dto\Kfc\KfcGridInputDto;
use App\Dto\Kfc\KfcGridItemInputDto;
use App\State\Kfc\KfcGridProcessor;
use App\State\Kfc\KfcGridProvider;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class KfcGridProcessorTest extends TestCase
{
    private MockObject $connection;

    private KfcGridProvider $gridProvider;

    private KfcGridProcessor $processor;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);

        // KfcGridProvider is readonly — create a real instance with a mock connection
        // that returns empty results (for the provide() call after saving)
        $providerConnection = $this->createMock(Connection::class);
        $providerConnection->method('fetchAllAssociative')->willReturn([]);
        $this->gridProvider = new KfcGridProvider($providerConnection);

        $this->processor = new KfcGridProcessor(
            $this->connection,
            $this->gridProvider,
        );
    }

    public function testUpdatesExistingGrid(): void
    {
        $executedStatements = [];
        $this->connection->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params) use (&$executedStatements) {
                $executedStatements[] = ['sql' => $sql, 'params' => $params];
                return 1;
            });

        $input = new KfcGridInputDto();
        $grid = new KfcGridItemInputDto();
        $grid->id = 5;
        $grid->text = 'Updated Grid';
        $grid->bunky = [];
        $input->grids = [$grid];

        $this->processor->process($input, new Post());

        // Should UPDATE grid, then DELETE old cells
        $this->assertCount(2, $executedStatements);
        $this->assertStringContainsString('UPDATE obchod_mrizky', $executedStatements[0]['sql']);
        $this->assertSame('Updated Grid', $executedStatements[0]['params']['text']);
        $this->assertStringContainsString('DELETE FROM obchod_bunky', $executedStatements[1]['sql']);
    }

    public function testCreatesNewGridWithNegativeId(): void
    {
        $executedStatements = [];
        $this->connection->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params) use (&$executedStatements) {
                $executedStatements[] = ['sql' => $sql, 'params' => $params];
                return 1;
            });
        $this->connection->method('lastInsertId')->willReturn('99');

        $input = new KfcGridInputDto();
        $grid = new KfcGridItemInputDto();
        $grid->id = -1; // negative = new
        $grid->text = 'New Grid';
        $grid->bunky = [];
        $input->grids = [$grid];

        $this->processor->process($input, new Post());

        // Should INSERT grid, then DELETE cells for new ID
        $this->assertStringContainsString('INSERT INTO obchod_mrizky', $executedStatements[0]['sql']);
        $this->assertSame('New Grid', $executedStatements[0]['params']['text']);
    }

    public function testInsertsCellsForGrid(): void
    {
        $executedStatements = [];
        $this->connection->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params) use (&$executedStatements) {
                $executedStatements[] = ['sql' => $sql, 'params' => $params];
                return 1;
            });

        $cell = new KfcGridCellInputDto();
        $cell->typ = 0;
        $cell->text = 'Tričko';
        $cell->barva = '#f4bb57';
        $cell->barvaText = '#000';
        $cell->cilId = 42;

        $input = new KfcGridInputDto();
        $grid = new KfcGridItemInputDto();
        $grid->id = 1;
        $grid->text = 'Test';
        $grid->bunky = [$cell];
        $input->grids = [$grid];

        $this->processor->process($input, new Post());

        // UPDATE grid + DELETE old cells + INSERT new cell = 3 statements
        $this->assertCount(3, $executedStatements);
        $insertStatement = $executedStatements[2];
        $this->assertStringContainsString('INSERT INTO obchod_bunky', $insertStatement['sql']);
        $this->assertSame(0, $insertStatement['params']['typ']);
        $this->assertSame('#f4bb57', $insertStatement['params']['barva']);
        $this->assertSame(42, $insertStatement['params']['cilId']);
    }

    public function testMapsNegativeGridIdInCellTargets(): void
    {
        $callCount = 0;
        $this->connection->method('executeStatement')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                return 1;
            });
        $this->connection->method('lastInsertId')->willReturn('77');

        // Grid with negative ID
        $newGrid = new KfcGridItemInputDto();
        $newGrid->id = -1;
        $newGrid->text = 'Sub-page';
        $newGrid->bunky = [];

        // Another grid with a cell pointing to the new grid
        $cell = new KfcGridCellInputDto();
        $cell->typ = 1; // page link
        $cell->cilId = -1; // points to the new grid (negative ID)

        $mainGrid = new KfcGridItemInputDto();
        $mainGrid->id = 1;
        $mainGrid->text = 'Main';
        $mainGrid->bunky = [$cell];

        $input = new KfcGridInputDto();
        $input->grids = [$newGrid, $mainGrid];

        // Capture the INSERT cell params
        $insertedCellParams = null;
        $this->connection->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params) use (&$insertedCellParams) {
                if (str_contains($sql, 'INSERT INTO obchod_bunky')) {
                    $insertedCellParams = $params;
                }
                return 1;
            });

        $this->processor->process($input, new Post());

        // The cell's cilId should be mapped from -1 to 77 (the real DB ID)
        $this->assertNotNull($insertedCellParams, 'Cell should have been inserted');
        $this->assertSame(77, $insertedCellParams['cilId']);
    }
}
