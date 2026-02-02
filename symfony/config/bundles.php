<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nextras\Migrations\Bridges\SymfonyBundle\NextrasMigrationsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MakerBundle\MakerBundle;
use Zenstruck\Foundry\ZenstruckFoundryBundle;

return [
    FrameworkBundle::class => [
        'all' => true,
    ],
    DoctrineBundle::class => [
        'all' => true,
    ],
    MakerBundle::class => [
        'dev' => true,
    ],
    ZenstruckFoundryBundle::class => [
        'dev'  => true,
        'test' => true,
    ],
    NextrasMigrationsBundle::class => [
        'all' => true,
    ],
];
