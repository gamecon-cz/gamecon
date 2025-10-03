<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\Report;
use App\Repository\ReportRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Report>
 *
 * @method        Report|Proxy create(array|callable $attributes = [])
 * @method static Report|Proxy createOne(array $attributes = [])
 * @method static Report|Proxy find(object|array|mixed $criteria)
 * @method static Report|Proxy findOrCreate(array $attributes)
 * @method static Report|Proxy first(string $sortedField = 'id')
 * @method static Report|Proxy last(string $sortedField = 'id')
 * @method static Report|Proxy random(array $attributes = [])
 * @method static Report|Proxy randomOrCreate(array $attributes = [])
 * @method static ReportRepository|ProxyRepositoryDecorator repository()
 * @method static Report[]|Proxy[] all()
 * @method static Report[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Report[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static Report[]|Proxy[] findBy(array $attributes)
 * @method static Report[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static Report[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class ReportFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Report::class;
    }

    protected function defaults(): array
    {
        return [
            'skript' => self::faker()->unique()->word(),
            'nazev' => self::faker()->sentence(),
            'formatXlsx' => true,
            'formatHtml' => true,
            'viditelny' => true,
        ];
    }
}
