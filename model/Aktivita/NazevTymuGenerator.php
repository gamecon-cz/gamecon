<?php

namespace Gamecon\Aktivita;

class NazevTymuGenerator
{
    private const ADJECTIVE = [
        'Opilí', 'Zmatení', 'Prokletí', 'Chaotičtí', 'Bezzubí', 'Mastní',
        'Legendární', 'Nelegální', 'Zapomenutí', 'Přismahlí',
        'Podezřelí', 'Ultra Temní', 'Totálně Ztracení', 'Kriticky Neúspěšní',
        'Finančně Nestabilní', 'Morálně Flexibilní',
    ];

    private const CREATURE = [
        'Goblini', 'Koboldi', 'Mimíci', 'Kostlivci', 'Draci', 'Trolové',
        'Kultisti', 'Kuřecí Wyverny', 'Démoni z Wishe', 'Daňoví Nekromanti',
        'Loot Goblini', 'Sklepení Rasové', 'Orkové na Brigádě',
    ];

    private const CLASSES = [
        'Bardové', 'Paladini', 'Warlockové', 'Barbaři', 'Rogueové', 'Klerici',
        'Druidové', 'Sorcererští Dlužníci', 'PvP Mágové', 'Multiclass Tragédie',
    ];

    private const PLACE = [
        'z Bahenní Lhoty', 'u Kritfailu', 'z Dolního Dungeonu',
        'z Taverny U Mimíka', 'z Koboldího Sklepa', 'od Posledního Checkpointu',
        'z Příkopu Zapomnění',
    ];

    private const ITEM = [
        'Rozbitých Kostek', 'Sedmi Sudů', 'Jednoho Spellslotu',
        'Pochybného Ležáku', 'Zakázaného Guláše', 'Lootu Bílé Kvality',
        'Tří Neúspěšných Saving Throwů',
    ];

    private const PROBLEM = [
        'co nečetli pravidla',
        'co zapálili hospodu',
        'co mají permanentní disadvantage',
        'co bojovali s dveřma 40 minut',
    ];

    private const TITLE = [
        'Reloaded', 'Ultimate Edition', 's.r.o.', 'Unlimited',
        'Remastered', 'HD', 'No Healer Run',
    ];

    public static function generuj(): string
    {
        $templates = [
            fn() => self::random(self::ADJECTIVE) . ' ' . self::random(self::CREATURE),
            fn() => self::random(self::ADJECTIVE) . ' ' . self::random(self::CLASSES),
            fn() => self::random(self::CREATURE) . ' ' . self::random(self::PLACE),
            fn() => self::random(self::CLASSES) . ' ' . self::random(self::PLACE),
            fn() => self::random(self::ADJECTIVE) . ' ' . self::random(self::CREATURE) . ' ' . self::random(self::PLACE),
            fn() => self::random(self::CREATURE) . ' ' . self::random(self::PROBLEM),
            fn() => self::random(self::CLASSES) . ' ' . self::random(self::PROBLEM),
            fn() => 'Bratrstvo ' . self::random(self::ITEM),
            fn() => 'Řád ' . self::random(self::ITEM),
            fn() => self::random(self::ADJECTIVE) . ' ' . self::random(self::CLASSES) . ' ' . self::random(self::PROBLEM),
            fn() => self::random(self::CREATURE) . ' & ' . self::random(self::CLASSES),
            fn() => self::random(self::CREATURE) . ' ' . self::random(self::TITLE),
        ];

        $name = self::random($templates)();

        if (self::chance(35)) {
            $name .= ' ' . self::random(self::TITLE);
        }

        return $name;
    }

    /**
     * @template T
     * @param array<int, T> $arr
     * @return T
     */
    private static function random(array $arr): mixed
    {
        return $arr[array_rand($arr)];
    }

    private static function chance(int $percent): bool
    {
        return mt_rand(0, 99) < $percent;
    }
}
