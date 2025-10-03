<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\QuickReport;
use App\Repository\QuickReportRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<QuickReport>
 *
 * @method        QuickReport|Proxy create(array|callable $attributes = [])
 * @method static QuickReport|Proxy createOne(array $attributes = [])
 * @method static QuickReport|Proxy find(object|array|mixed $criteria)
 * @method static QuickReport|Proxy findOrCreate(array $attributes)
 * @method static QuickReport|Proxy first(string $sortedField = 'id')
 * @method static QuickReport|Proxy last(string $sortedField = 'id')
 * @method static QuickReport|Proxy random(array $attributes = [])
 * @method static QuickReport|Proxy randomOrCreate(array $attributes = [])
 * @method static QuickReportRepository|ProxyRepositoryDecorator repository()
 * @method static QuickReport[]|Proxy[] all()
 * @method static QuickReport[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static QuickReport[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static QuickReport[]|Proxy[] findBy(array $attributes)
 * @method static QuickReport[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static QuickReport[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class QuickReportFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return QuickReport::class;
    }

    protected function defaults(): array
    {
        return [
            'nazev' => self::faker()->sentence(),
            'dotaz' => 'SELECT 1',
            'formatXlsx' => true,
            'formatHtml' => true,
        ];
    }
}
