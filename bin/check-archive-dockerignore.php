#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * The archive image ships a committed vendor/ and runs no composer install,
 * so a vendor dir excluded in .dockerignore is simply gone at runtime. The
 * kernel boots with an empty APP_ENV — read as dev — and loads the bundles
 * config/bundles.php registers for it, so excluding anything they need
 * transitively turns every kernel-booting page into a 500 that no
 * autoloader dump can repair. Three such outages shipped before this check
 * existed: MakerBundle, then ZenstruckFoundryBundle, then Faker\Factory.
 */

$root = dirname(__DIR__);
$lock = json_decode((string) file_get_contents($root . '/composer.lock'), true, 512, JSON_THROW_ON_ERROR);

$byName = [];
foreach ([...$lock['packages'] ?? [], ...$lock['packages-dev'] ?? []] as $package) {
    $byName[$package['name']] = $package;
}

$bundlesFile = $root . '/symfony/config/bundles.php';
if (! is_file($bundlesFile)) {
    echo "✓ no symfony/config/bundles.php — this year boots no kernel\n";

    exit(0);
}

preg_match_all('~^\s*([A-Za-z\\\\]+)::class~m', (string) file_get_contents($bundlesFile), $matches);

$queue = [];
foreach ($matches[1] as $class) {
    $vendorDir = strtolower(explode('\\', $class)[0]);
    foreach (array_keys($byName) as $name) {
        if (explode('/', $name)[0] === $vendorDir) {
            $queue[] = $name;
        }
    }
}

$required = [];
while ($queue !== []) {
    $name = array_pop($queue);
    if (isset($required[$name]) || ! isset($byName[$name])) {
        continue;
    }
    $required[$name] = true;
    foreach (array_keys($byName[$name]['require'] ?? []) as $dependency) {
        $queue[] = $dependency;
    }
}

$needed = [];
foreach (array_keys($required) as $name) {
    $needed[explode('/', $name)[0]] = true;
}

$excluded = [];
foreach (file($root . '/.dockerignore', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if (str_starts_with($line, 'vendor/')) {
        $excluded[] = rtrim(substr($line, strlen('vendor/')), '/');
    }
}

$broken = array_intersect($excluded, array_keys($needed));

if ($broken !== []) {
    fwrite(STDERR, ".dockerignore excludes vendor dirs the kernel needs — every kernel-booting page would 500:\n");
    foreach ($broken as $dir) {
        fwrite(STDERR, "  vendor/{$dir}\n");
    }

    exit(1);
}

echo '✓ .dockerignore leaves the kernel intact (' . count($needed) . " vendor dirs required)\n";

exit(0);
