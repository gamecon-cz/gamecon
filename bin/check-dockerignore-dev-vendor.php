#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Dev-only vendor directories are excluded from the preview build context
 * (see .dockerignore). The list is hand-written, so a newly added dev
 * package silently grows the image back — nothing fails, the regression is
 * only visible if someone re-measures. This compares the list against
 * composer.lock so CI catches the drift instead.
 */
$root = dirname(__DIR__);
$lock = json_decode((string) file_get_contents($root . '/composer.lock'), true, 512, JSON_THROW_ON_ERROR);

$vendorDirsOf = static function (array $packages): array {
    $dirs = [];
    foreach ($packages as $package) {
        $dirs[] = explode('/', $package['name'])[0];
    }

    return array_unique($dirs);
};

$production = $vendorDirsOf($lock['packages'] ?? []);
$development = $vendorDirsOf($lock['packages-dev'] ?? []);

// A vendor dir shared with a production package can never be excluded.
$devOnly = array_diff($development, $production);

// The metapackage filter below reads vendor/, so without it every package
// would look like a metapackage and the check would pass having verified
// nothing.
if (! is_dir($root . '/vendor')) {
    fwrite(STDERR, "vendor/ is missing — run composer install before this check.\n");

    exit(1);
}

// Metapackages install no files, so there is nothing to exclude for them.
$devOnly = array_filter(
    $devOnly,
    static fn (string $dir): bool => is_dir($root . '/vendor/' . $dir),
);

$excluded = [];
foreach (file($root . '/.dockerignore', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), 'vendor/')) {
        $excluded[] = substr(trim($line), strlen('vendor/'));
    }
}

$missing = array_diff($devOnly, $excluded);
$overreaching = array_intersect($excluded, $production);

$exitCode = 0;

if ($overreaching !== []) {
    fwrite(STDERR, "Production packages must not be excluded from the build context:\n");
    foreach ($overreaching as $dir) {
        fwrite(STDERR, "  vendor/{$dir}\n");
    }
    $exitCode = 1;
}

if ($missing !== []) {
    fwrite(STDERR, "Dev-only packages missing from .dockerignore (preview image grows needlessly):\n");
    foreach ($missing as $dir) {
        fwrite(STDERR, "  vendor/{$dir}\n");
    }
    $exitCode = 1;
}

if ($exitCode === 0) {
    echo '✓ .dockerignore matches composer.lock (' . count($devOnly) . " dev-only vendor dirs excluded)\n";
}

exit($exitCode);
