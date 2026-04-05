<?php

/*
 * This file is part of the Symfony MakerBundle package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MakerBundle\Util;

use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
class EnumHelper
{
    public function __construct(
        private string $directory,
        private string $namespace = 'App',
    ) {
        $this->directory = str_replace(['/', '\\'], \DIRECTORY_SEPARATOR, $directory);
    }

    public function getAllEnums(): array
    {
        $enums = [];
        $finder = new Finder();

        // Check all PHP files in the directory
        try {
            $finder->files()
                ->in($this->directory)
                ->name('*.php');
        } catch (DirectoryNotFoundException $e) {
            return [];
        }

        foreach ($finder as $file) {
            $relativePath = str_replace([$this->directory.\DIRECTORY_SEPARATOR, '.php'], ['', ''], $file->getRealPath());
            $className = $this->namespace.'\\'.str_replace('/', '\\', $relativePath);

            if (enum_exists($className)) {
                $enums[] = $className;
            }
        }

        return $enums;
    }
}
