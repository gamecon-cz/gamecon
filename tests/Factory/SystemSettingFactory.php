<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use App\Entity\SystemSetting;
use App\Repository\SystemSettingRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<SystemSetting>
 *
 * @method        SystemSetting|Proxy create(array|callable $attributes = [])
 * @method static SystemSetting|Proxy createOne(array $attributes = [])
 * @method static SystemSetting|Proxy find(object|array|mixed $criteria)
 * @method static SystemSetting|Proxy findOrCreate(array $attributes)
 * @method static SystemSetting|Proxy first(string $sortedField = 'id')
 * @method static SystemSetting|Proxy last(string $sortedField = 'id')
 * @method static SystemSetting|Proxy random(array $attributes = [])
 * @method static SystemSetting|Proxy randomOrCreate(array $attributes = [])
 * @method static SystemSettingRepository|ProxyRepositoryDecorator repository()
 * @method static SystemSetting[]|Proxy[] all()
 * @method static SystemSetting[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static SystemSetting[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static SystemSetting[]|Proxy[] findBy(array $attributes)
 * @method static SystemSetting[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static SystemSetting[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class SystemSettingFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return SystemSetting::class;
    }

    protected function defaults(): array
    {
        return [
            'klic' => self::faker()->unique()->word(),
            'hodnota' => self::faker()->word(),
            'vlastni' => false,
            'datovyTyp' => 'string',
            'nazev' => self::faker()->sentence(),
            'popis' => self::faker()->text(),
            'zmenaKdy' => self::faker()->dateTime(),
            'skupina' => self::faker()->word(),
            'poradi' => self::faker()->numberBetween(1, 100),
            'pouzeProCteni' => false,
            'rocnikNastaveni' => -1,
        ];
    }
}
