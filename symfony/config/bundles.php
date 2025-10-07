<?php

declare(strict_types=1);

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => [
        'all' => true,
    ],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => [
        'all' => true,
    ],
    Symfony\Bundle\MakerBundle\MakerBundle::class => [
        'dev' => true,
    ],
    Zenstruck\Foundry\ZenstruckFoundryBundle::class => [
        'dev'  => true,
        'test' => true,
    ],
    Nextras\Migrations\Bridges\SymfonyBundle\NextrasMigrationsBundle::class => [
        'all' => true,
    ],
];
