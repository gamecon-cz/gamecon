<?php declare(strict_types=1);

namespace Granam\Tools;

use Granam\Strict\Object\StrictObject;

class DirName extends StrictObject
{
    public static function getPathWithResolvedParents(string $folder): string
    {
        $hasRoot = $folder[0] === '/';
        $parts = preg_split('~/~', $folder, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $cleansedParts = [];
        $skipParts = 0;
        foreach (array_reverse($parts) as $part) {
            if ($part === '.') {
                continue;
            }
            if ($part === '..') {
                $skipParts++;
                continue;
            }
            if ($skipParts > 0) {
                $skipParts--;
                continue;
            }
            $cleansedParts[] = $part;
        }
        $cleansedPath = implode('/', array_reverse($cleansedParts));
        if ($hasRoot) {
            $cleansedPath = '/' . $cleansedPath;
        }
        return $cleansedPath;
    }
}
