#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * vendor/ is committed, so a dependency bump has to commit what composer did to it together
 * with composer.lock. When it does not, every `composer install` afterwards rewrites tracked
 * files and dirties `git status` in every worktree. This compares what
 * vendor/composer/installed.json says is installed with the lock, so CI catches that bump.
 *
 * Usage: check-vendor-matches-lock.php [project root]
 */
$root = $argv[1] ?? dirname(__DIR__);

$readJson = static function (string $path): array {
    // Comparing nothing would pass, and a pass says vendor/ is current.
    if (! is_file($path)) {
        fwrite(STDERR, "{$path} is missing, there is nothing to compare.\n");

        exit(1);
    }

    return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
};

$lock = $readJson($root . '/composer.lock');
$installed = $readJson($root . '/vendor/composer/installed.json');

$indexByName = static function (array $packages): array {
    $indexed = [];
    foreach ($packages as $package) {
        $indexed[$package['name']] = $package;
    }
    ksort($indexed);

    return $indexed;
};

$inLock = $indexByName([...($lock['packages'] ?? []), ...($lock['packages-dev'] ?? [])]);
$inVendor = $indexByName($installed['packages'] ?? []);

$commitOf = static fn (array $package): string => (string) ($package['dist']['reference'] ?? $package['source']['reference'] ?? '?');
$shortened = static fn (string $commit): string => substr($commit, 0, 12);

$missing = array_diff_key($inLock, $inVendor);
$unknown = array_diff_key($inVendor, $inLock);

$different = [];
foreach (array_intersect_key($inLock, $inVendor) as $name => $package) {
    $installedPackage = $inVendor[$name];
    if ($package['version'] !== $installedPackage['version']) {
        $different[] = "{$name}: composer.lock {$package['version']}, vendor/ {$installedPackage['version']}";
    } elseif ($commitOf($package) !== $commitOf($installedPackage)) {
        $different[] = "{$name} {$package['version']}: composer.lock commit {$shortened($commitOf($package))}, vendor/ commit {$shortened($commitOf($installedPackage))}";
    }
}

// A package moved between require and require-dev changes installed.json and installed.php too.
$devInLock = array_map(static fn (array $package): string => $package['name'], $lock['packages-dev'] ?? []);
$devInVendor = $installed['dev-package-names'] ?? [];
sort($devInLock);
sort($devInVendor);
$movedBetweenProductionAndDev = $devInLock === $devInVendor
    ? []
    : array_unique([...array_diff($devInLock, $devInVendor), ...array_diff($devInVendor, $devInLock)]);

// installed.json can be committed while the directory of a package a bump added is not.
$withoutDirectory = [];
foreach ($inVendor as $name => $package) {
    $installPath = $package['install-path'] ?? null;
    if ($installPath !== null && ! is_dir($root . '/vendor/composer/' . $installPath)) {
        $withoutDirectory[] = $name;
    }
}

$report = static function (string $heading, array $lines): bool {
    if ($lines === []) {
        return false;
    }
    fwrite(STDERR, $heading . "\n");
    foreach ($lines as $line) {
        fwrite(STDERR, "  {$line}\n");
    }

    return true;
};

$failed = [
    $report('Differ between composer.lock and vendor/:', $different),
    $report('In composer.lock, missing from vendor/composer/installed.json:', array_keys($missing)),
    $report('In vendor/composer/installed.json, not in composer.lock:', array_keys($unknown)),
    $report('Listed in installed.json, directory missing from vendor/:', $withoutDirectory),
    $report('Dev status differs (composer.lock packages-dev against installed.json dev-package-names):', $movedBetweenProductionAndDev),
];

if (in_array(true, $failed, true)) {
    fwrite(STDERR, "\nRun ./bin-docker/composer install and commit what it changed in vendor/ together with composer.lock.\n");

    exit(1);
}

echo '✓ vendor/ matches composer.lock (' . count($inLock) . " packages)\n";
