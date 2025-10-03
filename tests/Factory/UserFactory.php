<?php

namespace Gamecon\Tests\Factory;

use App\Entity\Enum\GenderEnum;
use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<User>
 */
final class UserFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return User::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array | callable
    {
        return [
            'datumNarozeni'                    => self::faker()->dateTime(),
            'email'                            => self::faker()->text(255),
            'forumRazeni'                      => self::faker()->randomElement(['v', 's']),
            'hesloMd5'                         => self::faker()->text(255),
            'infopultPoznamka'                 => self::faker()->text(128),
            'jmeno'                            => self::faker()->text(100),
            'login'                            => self::faker()->text(255),
            'mesto'                            => self::faker()->text(100),
            'mrtvyMail'                        => self::faker()->boolean(),
            'nechceMaily'                      => self::faker()->dateTime(),
            'op'                               => self::faker()->text(4096),
            'pohlavi'                          => self::faker()->randomElement(GenderEnum::cases()),
            'pomocTyp'                         => self::faker()->text(64),
            'pomocVice'                        => self::faker()->text(),
            'potvrzeniProtiCovid19OverenoKdy'  => self::faker()->dateTime(),
            'potvrzeniProtiCovid19PridanoKdy'  => self::faker()->dateTime(),
            'potvrzeniZakonnehoZastupce'       => self::faker()->dateTime(),
            'potvrzeniZakonnehoZastupceSoubor' => self::faker()->dateTime(),
            'poznamka'                         => self::faker()->text(4096),
            'prijmeni'                         => self::faker()->text(100),
            'psc'                              => self::faker()->text(20),
            'random'                           => self::faker()->text(20),
            'registrovan'                      => self::faker()->dateTime(),
            'stat'                             => self::faker()->randomNumber(),
            'statniObcanstvi'                  => self::faker()->text(64),
            'telefon'                          => self::faker()->text(100),
            'typDokladuTotoznosti'             => self::faker()->text(16),
            'ubytovanS'                        => self::faker()->text(255),
            'uliceACp'                         => self::faker()->text(255),
            'zRychloregistrace'                => self::faker()->boolean(),
            'zustatek'                         => self::faker()->randomNumber(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this// ->afterInstantiate(function(User $user): void {})
            ;
    }
}
